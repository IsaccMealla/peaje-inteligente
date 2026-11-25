<?php

namespace App\Http\Controllers\Api;

use App\Events\NuevoRegistroPeaje;
use App\Http\Controllers\Controller;
use App\Models\Alerta;
use App\Models\Caseta;
use App\Models\Registro;
use App\Models\Tarifa;
use App\Models\Usuario;
use App\Models\VehiculoRegistrado;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

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

        if (! $usuario) {
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

        $archivoImagen = $request->file('imagen');
        $contenidoImagen = file_get_contents($archivoImagen->getRealPath());
        $imagenDataUri = $this->crearMiniaturaDataUri(
            $contenidoImagen,
            $archivoImagen->getRealPath()
        );

        // 2. Enviar imagen al microservicio de IA (FastAPI)
        $iaUrl = rtrim(config('services.ia.url'), '/').'/inferir';
        $placa = null;
        $categoria = 'auto';
        $ejes = 2;
        $confianza = 0.0;
        $latencia = null;
        $pasajeros = 0;
        $latenciasModelos = [];
        $estado = 'procesado';

        try {
            $iaResponse = Http::timeout(30)
                ->attach(
                    'imagen',
                    $contenidoImagen,
                    $archivoImagen->hashName()
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
                $pasajeros = max(0, (int) ($resultado['pasajeros'] ?? 0));
                $latenciasModelos = $resultado['latencia_modelos'] ?? [];
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

        $categoriaTarifa = match ($categoria) {
            'moto' => 'moto',
            'minibus' => 'minibus',
            'bus' => 'bus',
            'camion' => $nombreTarifa,
            default => 'liviano',
        };
        $montoTarifa = match ($categoria) {
            'moto' => 5.00,
            'bus' => 30.00,
            'camion' => 25.00 * max(1, $ejes - 1),
            default => 15.00,
        };

        $tarifa = Tarifa::where('categoria_vehiculo', $categoriaTarifa)->first()
            ?? Tarifa::firstOrCreate(
                ['categoria_vehiculo' => $categoriaTarifa],
                ['monto' => $montoTarifa]
            );

        if ($confianza < 0.60 || empty($placa)) {
            $estado = 'revision';
        }

        $vehiculo = $placa !== null
            ? VehiculoRegistrado::where('placa', strtoupper($placa))->first()
            : null;
        $alertaEvasion = null;
        if ($placa !== null && ! $vehiculo) {
            $estado = 'revision';
            $alertaEvasion = [
                'tipo' => 'vehiculo_no_registrado',
                'mensaje' => "La placa {$placa} no está registrada en el padrón vehicular.",
            ];
        } elseif ($vehiculo && ! $vehiculo->activo) {
            $estado = 'revision';
            $alertaEvasion = [
                'tipo' => 'vehiculo_inactivo',
                'mensaje' => "La placa {$placa} pertenece a un vehículo inactivo.",
            ];
        } elseif ($vehiculo && (float) $vehiculo->saldo <= 0) {
            $estado = 'revision';
            $alertaEvasion = [
                'tipo' => 'saldo_insuficiente',
                'mensaje' => "La placa {$placa} tiene saldo insuficiente o el vehículo está inactivo.",
            ];
        }

        $registro = Registro::create([
            'usuario_id' => $request->user()->id,
            'tarifa_id' => $tarifa?->id,
            'placa_detectada' => $placa,
            'clase_vehiculo' => $nombreTarifa,
            'ejes' => $ejes,
            'pasajeros' => $pasajeros,
            'confianza' => $confianza,
            'latencia_ms' => $latencia,
            'imagen_url' => $imagenDataUri,
            'estado' => $estado,
            'caseta_id' => Caseta::where('activa', true)->value('id'),
        ]);

        if ($alertaEvasion !== null) {
            Alerta::create([
                'registro_id' => $registro->id,
                ...$alertaEvasion,
            ]);
        }

        // 4. Generar alerta si requiere revisión manual
        if ($estado === 'revision' && $alertaEvasion === null) {
            Alerta::create([
                'registro_id' => $registro->id,
                'tipo' => empty($placa) ? 'placa_no_legible' : 'baja_confianza',
                'mensaje' => "Registro #{$registro->id} requiere verificación manual (Confianza: ".round($confianza * 100).'%).',
            ]);
        }

        // 5. Transmitir en vivo al Dashboard web mediante Pusher
        event(new NuevoRegistroPeaje($registro));

        $registro->load('tarifa');
        $respuesta = [
            'mensaje' => 'Captura procesada',
            'latencia_modelos' => $latenciasModelos,
        ];

        if ($request->is('api/v1/pasos/frame')) {
            $respuesta['paso'] = [
                'id' => $registro->id,
                'placa_detectada' => $registro->placa_detectada,
                'clase_vehiculo' => $registro->clase_vehiculo,
                'ejes' => $registro->ejes,
                'pasajeros' => $registro->pasajeros,
                'confianza' => $registro->confianza,
                'estado' => $registro->estado,
                'tarifa' => $registro->tarifa,
            ];
        } else {
            $respuesta['registro'] = $registro;
        }

        return response()->json($respuesta);
    }

    private function crearMiniaturaDataUri(string $contenido, string $ruta): string
    {
        if (! function_exists('imagecreatefromstring')) {
            throw new RuntimeException('La extensión GD es necesaria para procesar capturas.');
        }

        $imagen = @imagecreatefromstring($contenido);
        if ($imagen === false) {
            throw new RuntimeException('No se pudo decodificar la imagen recibida.');
        }

        if (function_exists('exif_read_data')) {
            $metadatos = @exif_read_data($ruta);
            $rotacion = match ((int) ($metadatos['Orientation'] ?? 1)) {
                3 => 180,
                6 => -90,
                8 => 90,
                default => null,
            };
            if ($rotacion !== null) {
                $imagenOrientada = imagerotate($imagen, $rotacion, 0);
                if ($imagenOrientada === false) {
                    imagedestroy($imagen);
                    throw new RuntimeException('No se pudo orientar la captura.');
                }
                imagedestroy($imagen);
                $imagen = $imagenOrientada;
            }
        }

        $ancho = imagesx($imagen);
        $alto = imagesy($imagen);
        $escala = min(1, 480 / $ancho);
        $anchoMiniatura = max(1, (int) round($ancho * $escala));
        $altoMiniatura = max(1, (int) round($alto * $escala));
        $miniatura = imagecreatetruecolor($anchoMiniatura, $altoMiniatura);
        if ($miniatura === false) {
            imagedestroy($imagen);
            throw new RuntimeException('No se pudo crear la miniatura de la captura.');
        }

        try {
            if (! imagecopyresampled(
                $miniatura,
                $imagen,
                0,
                0,
                0,
                0,
                $anchoMiniatura,
                $altoMiniatura,
                $ancho,
                $alto
            )) {
                throw new RuntimeException('No se pudo redimensionar la captura.');
            }

            ob_start();
            try {
                if (! imagejpeg($miniatura, null, 75)) {
                    throw new RuntimeException('No se pudo comprimir la captura.');
                }

                $jpeg = ob_get_contents();
                if ($jpeg === false) {
                    throw new RuntimeException('No se pudo leer la captura comprimida.');
                }
            } finally {
                ob_end_clean();
            }
        } finally {
            imagedestroy($miniatura);
            imagedestroy($imagen);
        }

        return 'data:image/jpeg;base64,'.base64_encode($jpeg);
    }
}
