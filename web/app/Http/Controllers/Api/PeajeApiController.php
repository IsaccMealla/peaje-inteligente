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
        $rutaCompleta = storage_path('app/public/' . $rutaImagen);

        // 2. Enviar imagen al microservicio de IA (FastAPI)
        $iaUrl = rtrim(config('services.ia.url'), '/') . '/inferir';
        $placa = null;
        $clase = 'liviano';
        $confianza = 0.0;
        $estado = 'procesado';

        try {
            $iaResponse = Http::timeout(15)
                ->attach('file', file_get_contents($rutaCompleta), basename($rutaCompleta))
                ->post($iaUrl);

            if ($iaResponse->successful()) {
                $resultado = $iaResponse->json();
                $placa = $resultado['placa'] ?? null;
                $clase = strtolower($resultado['clase'] ?? 'liviano');
                $confianza = (float) ($resultado['confianza'] ?? 0.0);
            } else {
                $estado = 'revision';
            }
        } catch (\Exception $e) {
            $estado = 'revision';
        }

        // 3. Buscar tarifa correspondiente y guardar registro
        $tarifa = Tarifa::where('categoria_vehiculo', $clase)->first()
            ?? Tarifa::where('categoria_vehiculo', 'liviano')->first();

        if ($confianza < 0.60 || empty($placa)) {
            $estado = 'revision';
        }

        $registro = Registro::create([
            'usuario_id' => $request->user()->id,
            'tarifa_id' => $tarifa?->id,
            'placa_detectada' => $placa,
            'clase_vehiculo' => $clase,
            'confianza' => $confianza,
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