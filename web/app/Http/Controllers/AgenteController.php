<?php

namespace App\Http\Controllers;

use App\Models\Registro;
use App\Models\VehiculoRegistrado;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AgenteController extends Controller
{
    public function consultar(Request $request)
    {
        $validated = $request->validate([
            'texto' => ['nullable', 'string', 'max:50'],
            'imagen' => ['nullable', 'image', 'max:5120'],
        ]);

        $placaCruda = $validated['texto'] ?? null;
        $origen = 'texto';

        if ($request->hasFile('imagen')) {
            $origen = 'imagen';
            $inferenceUrl = rtrim((string) config('services.ia.url'), '/');

            if ($inferenceUrl === '') {
                return response()->json([
                    'estado' => 'Error de configuración',
                    'mensaje' => 'El servicio de inferencia no está configurado en el servidor.',
                ], 503);
            }

            try {
                $imagen = $request->file('imagen');
                $response = Http::timeout(25)
                    ->attach(
                        'imagen',
                        file_get_contents($imagen->getRealPath()),
                        $imagen->getClientOriginalName()
                    )
                    ->post($inferenceUrl.'/inferir');

                if (! $response->successful()) {
                    return response()->json([
                        'estado' => 'Servicio no disponible',
                        'mensaje' => 'El servicio de inferencia no pudo procesar la imagen. Intenta nuevamente o escribe la placa.',
                    ], 502);
                }

                $placaCruda = $response->json('placa');
                if (empty($placaCruda)) {
                    return response()->json([
                        'estado' => 'Imagen no legible',
                        'mensaje' => 'No se encontró una placa legible. Envía una foto más nítida o escribe la placa.',
                    ]);
                }
            } catch (\Throwable $exception) {
                report($exception);

                return response()->json([
                    'estado' => 'Servicio no disponible',
                    'mensaje' => 'No se pudo procesar la imagen. Intenta nuevamente o escribe la placa.',
                ], 502);
            }
        }

        if (empty($placaCruda)) {
            return response()->json([
                'estado' => 'Entrada vacía',
                'mensaje' => 'Escribe una placa o adjunta una imagen del vehículo.',
            ], 422);
        }

        $placa = $this->normalizarPlaca($placaCruda);

        if (! $this->esFormatoValido($placa)) {
            return response()->json([
                'estado' => 'Formato inválido',
                'placa_normalizada' => $placa,
                'mensaje' => "La placa '{$placa}' no tiene un formato válido (por ejemplo, 4021XYZ o 123ABC).",
            ]);
        }

        $registrado = VehiculoRegistrado::where('placa', $placa)->first();
        $pasos = Registro::with('tarifa')
            ->where('placa_detectada', $placa)
            ->latest()
            ->get();

        $sugerencias = [];
        if (! $registrado && $pasos->isEmpty()) {
            $variantes = $this->generarVariantesOcr($placa);
            if ($variantes !== []) {
                $sugerenciasRegistradas = VehiculoRegistrado::whereIn('placa', $variantes)
                    ->pluck('placa')
                    ->all();
                $sugerenciasPasos = Registro::whereIn('placa_detectada', $variantes)
                    ->distinct()
                    ->pluck('placa_detectada')
                    ->all();

                $sugerencias = array_values(array_unique(array_merge(
                    $sugerenciasRegistradas,
                    $sugerenciasPasos
                )));
            }
        }

        if ($registrado && $pasos->isNotEmpty()) {
            $ultimo = $pasos->first();
            $fecha = $ultimo->created_at
                ->timezone('America/La_Paz')
                ->format('d/m/Y H:i:s');
            $caseta = 'no registrada en la base de datos';
            $categoria = $ultimo->tarifa?->categoria_vehiculo
                ?? $ultimo->clase_vehiculo
                ?? 'no registrada';
            $monto = $ultimo->tarifa
                ? 'Bs. '.number_format((float) $ultimo->tarifa->monto, 2)
                : 'no registrado';

            return response()->json([
                'estado' => 'Registrada y con paso',
                'placa' => $placa,
                'origen' => $origen,
                'mensaje' => "La placa {$placa} está en el padrón (propietario: {$registrado->propietario}). Tiene {$pasos->count()} paso(s); el más reciente fue {$fecha}. Caseta: {$caseta}. Categoría: {$categoria}. Monto: {$monto}.",
                'sugerencias' => [],
            ]);
        }

        if ($registrado) {
            return response()->json([
                'estado' => 'Registrada sin paso',
                'placa' => $placa,
                'origen' => $origen,
                'mensaje' => "La placa {$placa} está en el padrón (propietario: {$registrado->propietario}, categoría: {$registrado->categoria}), pero no tiene pasos registrados.",
                'sugerencias' => [],
            ]);
        }

        if ($pasos->isNotEmpty()) {
            $ultimo = $pasos->first();
            $fecha = $ultimo->created_at
                ->timezone('America/La_Paz')
                ->format('d/m/Y H:i:s');

            return response()->json([
                'estado' => 'Identificada no registrada',
                'placa' => $placa,
                'origen' => $origen,
                'mensaje' => "La placa {$placa} aparece en {$pasos->count()} paso(s), el más reciente el {$fecha}, pero no figura en el padrón vehicular.",
                'sugerencias' => [],
            ]);
        }

        $mensaje = "La placa {$placa} no aparece en el padrón ni en los registros de paso.";
        if ($sugerencias !== []) {
            $mensaje .= ' ¿Quisiste decir '.implode(' o ', $sugerencias).'?';
        }

        return response()->json([
            'estado' => 'No existe',
            'placa' => $placa,
            'origen' => $origen,
            'mensaje' => $mensaje,
            'sugerencias' => $sugerencias,
        ]);
    }

    private function normalizarPlaca(string $texto): string
    {
        return preg_replace('/[^A-Z0-9]/', '', strtoupper(trim($texto))) ?? '';
    }

    private function esFormatoValido(string $placa): bool
    {
        return (bool) preg_match('/^[0-9OIBS]{3,4}[A-Z0185]{3}$/', $placa);
    }

    private function generarVariantesOcr(string $placa): array
    {
        $alternativas = [
            'O' => '0',
            '0' => 'O',
            'I' => '1',
            '1' => 'I',
            'B' => '8',
            '8' => 'B',
            'S' => '5',
            '5' => 'S',
        ];

        $caracteres = str_split($placa);
        $posiciones = [];
        foreach ($caracteres as $indice => $caracter) {
            if (isset($alternativas[$caracter])) {
                $posiciones[$indice] = $alternativas[$caracter];
            }
        }

        $variantes = [];
        foreach ($posiciones as $indice => $alternativa) {
            $copia = $caracteres;
            $copia[$indice] = $alternativa;
            $variantes[] = implode('', $copia);
        }

        $indices = array_keys($posiciones);
        for ($i = 0, $cantidad = count($indices); $i < $cantidad; $i++) {
            for ($j = $i + 1; $j < $cantidad; $j++) {
                $copia = $caracteres;
                $copia[$indices[$i]] = $posiciones[$indices[$i]];
                $copia[$indices[$j]] = $posiciones[$indices[$j]];
                $variantes[] = implode('', $copia);
            }
        }

        return array_values(array_unique($variantes));
    }
}
