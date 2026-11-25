<?php

namespace App\Http\Controllers\Api;

use App\Events\NuevoRegistroPeaje;
use App\Http\Controllers\Controller;
use App\Models\Alerta;
use App\Models\Registro;
use App\Models\Tarifa;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class PeajeApiController extends Controller
{
    public function loginGoogle(Request $request)
    {
        $request->validate(['id_token' => 'required|string']);

        $response = Http::get('https://oauth2.googleapis.com/tokeninfo', [
            'id_token' => $request->id_token,
        ]);

        if ($response->failed()) {
            return response()->json(['error' => 'Token de Google inválido'], 401);
        }

        $payload = $response->json();
        $email = $payload['email'] ?? null;

        $webClientId = config('services.google.client_id');
        if ($webClientId && (($payload['aud'] ?? null) !== $webClientId)) {
            return response()->json([
                'error' => 'El token no pertenece al cliente web de Google configurado',
            ], 401);
        }

        $usuario = Usuario::where('email_google', $email)
            ->where('activo', true)
            ->first();

        if (!$usuario) {
            return response()->json(['error' => 'Usuario no autorizado o inactivo'], 403);
        }

        $token = $usuario->createToken('mobile')->plainTextToken;

        return response()->json([
            'token' => $token,
            'usuario' => [
                'id' => $usuario->id,
                'nombre' => $usuario->nombre,
                'email' => $usuario->email_google,
                'rol' => $usuario->rol,
            ],
        ]);
    }

    public function procesar(Request $request)
    {
        $request->validate([
            'imagen' => 'required|image|max:10240',
        ]);

        // 1. Guardar imagen en storage/app/public/capturas
        $rutaImagen = $request->file('imagen')->store('capturas', 'public');

        // 2. Enviar imagen al microservicio de IA (FastAPI)
        $iaUrl = rtrim(config('services.ia.url'), '/') . '/inferir';
        $placa = null;
        $categoria = 'auto';
        $ejes = 2;
        $confianza = 0.0;
        $latencia = null;
        $estado = 'procesado';

        try {
            $iaResponse = Http::timeout(15)
                ->attach(
                    'file',
                    file_get_contents($request->file('imagen')->getRealPath()),
                    basename($rutaImagen)
                )
                ->post($iaUrl);

            if ($iaResponse->successful()) {
                $resultado = $iaResponse->json();
                $placa = $resultado['placa'] ?? null;
                $categoria = strtolower($resultado['categoria'] ?? 'auto');
                $ejes = (int) ($resultado['ejes'] ?? 2);
                $confianza = (float) ($resultado['confianza'] ?? 0.0);
                $latencia = isset($resultado['latencia'])
                    ? (float) $resultado['latencia']
                    : null;
            } else {
                $estado = 'revision';
            }
        } catch (\Exception $e) {
            $estado = 'revision';
        }

        // 3. Buscar tarifa correspondiente y guardar registro
        $nombreTarifa = match ($categoria) {
            'moto' => 'Motocicleta',
            'minibus' => 'Minibús',
            'bus' => 'Bus',
            'camion' => $ejes >= 3 ? 'Pesado (3+ ejes)' : 'Pesado (2 ejes)',
            default => 'Liviano',
        };

        $tarifa = Tarifa::where('categoria_vehiculo', $nombreTarifa)->first()
            ?? Tarifa::firstOrCreate(
                ['categoria_vehiculo' => $nombreTarifa],
                [
                    'monto' => $categoria === 'camion'
                        ? 25.00 * max(1, $ejes - 1)
                        : 15.00,
                ]
            );

        if ($confianza < 0.60 || empty($placa)) {
            $estado = 'revision';
        }

        $registro = Registro::create([
            'usuario_id' => $request->user()->id,
            'tarifa_id' => $tarifa?->id,
            'placa_detectada' => $placa,
            'clase_vehiculo' => $nombreTarifa,
            'ejes' => $ejes,
            'confianza' => $confianza,
            'latencia_ms' => $latencia,
            'imagen_url' => $rutaImagen,
            'estado' => $estado,
        ]);

        // 4. Generar alerta si requiere revisión manual
        if ($estado === 'revision') {
            Alerta::create([
                'registro_id' => $registro->id,
                'tipo' => empty($placa) ? 'placa_no_legible' : 'baja_confianza',
                'mensaje' => "Registro #{$registro->id} requiere verificación manual (Confianza: " . round($confianza * 100) . "%).",
            ]);
        }

        // 5. Transmitir en vivo al Dashboard web mediante Pusher
        event(new NuevoRegistroPeaje($registro));

        return response()->json([
            'mensaje' => 'Captura procesada',
            'registro' => $registro->load('tarifa'),
        ]);
    }
}