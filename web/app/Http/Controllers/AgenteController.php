<?php

namespace App\Http\Controllers;

use App\Models\Registro;
use App\Models\Tarifa;
use App\Models\VehiculoRegistrado;
use App\Services\InferenceHealthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;

class AgenteController extends Controller
{
    public function historial(string $placa)
    {
        $placa = $this->normalizarPlaca($placa);
        $pasos = Registro::with('tarifa')
            ->where('placa_detectada', $placa)
            ->latest()
            ->paginate(30);

        return response()->json([
            'placa' => $placa,
            'total' => $pasos->total(),
            'pasos' => $pasos,
        ]);
    }

    public function pagina(): View
    {
        return view('agente', ['historial' => session('agente_historial', [])]);
    }

    public function nuevaSesion(): RedirectResponse
    {
        session()->forget('agente_historial');

        return redirect()->route('agente.index')->with('estado', 'Se inició una nueva sesión.');
    }

    public function chatWeb(Request $request, InferenceHealthService $health): JsonResponse
    {
        $validated = $request->validate([
            'mensaje' => ['nullable', 'string', 'max:1000'],
            'imagenes' => ['nullable', 'array', 'max:5'],
            'imagenes.*' => ['image', 'max:5120'],
        ]);
        $mensaje = trim($validated['mensaje'] ?? '');
        $imagenes = $request->file('imagenes', []);

        if ($mensaje === '' && $imagenes === []) {
            return response()->json(['mensaje' => 'Escribe una consulta o adjunta una fotografía.'], 422);
        }

        $resultados = [];
        if ($imagenes !== []) {
            foreach ($imagenes as $imagen) {
                $consulta = $this->consultar(Request::create(
                    '/agente/consultar',
                    'POST',
                    [],
                    [],
                    ['imagen' => $imagen]
                ));
                $resultado = $consulta->getData(true);
                $resultados[] = [
                    'archivo' => $imagen->getClientOriginalName(),
                    ...$resultado,
                ];
            }
            $respuesta = collect($resultados)->map(function (array $resultado): string {
                $analisis = $resultado['analisis'] ?? [];
                $placa = $analisis['placa'] ?? $resultado['placa'] ?? 'No legible';

                return "{$resultado['archivo']}: {$resultado['estado']} — placa {$placa}; ".
                    ($analisis['categoria'] ?? 'categoría no disponible').', '.
                    ($analisis['ejes'] ?? '—').' ejes, '.
                    ($analisis['pasajeros'] ?? '—').' pasajeros, OCR '.
                    round((float) ($analisis['confianza_ocr'] ?? 0) * 100).'% y tarifa '.
                    (isset($analisis['tarifa']) ? 'Bs. '.number_format((float) $analisis['tarifa'], 2) : 'no configurada').
                    '. '.$resultado['mensaje'];
            })->implode("\n");
        } else {
            $respuesta = $this->responderConsultaRapida($mensaje, $health);
        }

        $historial = session('agente_historial', []);
        $historial[] = [
            'rol' => 'user',
            'contenido' => $mensaje !== '' ? $mensaje : 'Análisis de '.count($imagenes).' fotografía(s)',
            'hora' => now()->timezone('America/La_Paz')->format('H:i'),
        ];
        $historial[] = [
            'rol' => 'assistant',
            'contenido' => $respuesta,
            'hora' => now()->timezone('America/La_Paz')->format('H:i'),
        ];
        $historial = array_slice($historial, -40);
        session()->put('agente_historial', $historial);

        return response()->json([
            'respuesta' => $respuesta,
            'resultados' => $resultados,
            'historial' => $historial,
        ]);
    }

    public function consultar(Request $request)
    {
        $validated = $request->validate([
            'texto' => ['nullable', 'string', 'max:50'],
            'imagen' => ['nullable', 'image', 'max:5120'],
        ]);

        $placaCruda = $validated['texto'] ?? null;
        $origen = 'texto';
        $analisis = [];

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

                $datosInferencia = $response->json();
                $placaCruda = $datosInferencia['placa'] ?? null;
                $analisis = [
                    'analisis' => [
                        'placa' => $placaCruda,
                        'confianza_ocr' => (float) ($datosInferencia['confianza_ocr'] ?? $datosInferencia['confianza'] ?? 0),
                        'categoria' => $datosInferencia['categoria'] ?? null,
                        'ejes' => (int) ($datosInferencia['ejes'] ?? 0),
                        'pasajeros' => (int) ($datosInferencia['pasajeros'] ?? 0),
                        'latencia' => $datosInferencia['latencia'] ?? null,
                        'latencia_modelos' => $datosInferencia['latencia_modelos'] ?? [],
                    ],
                ];
                $categoriaTarifa = match ($analisis['analisis']['categoria']) {
                    'moto', 'auto', 'minibus', 'bus', 'camion' => $analisis['analisis']['categoria'],
                    default => 'auto',
                };
                $analisis['analisis']['tarifa'] = Tarifa::where('categoria_vehiculo', $categoriaTarifa)
                    ->where('ejes', $analisis['analisis']['ejes'])
                    ->value('monto');
                if (empty($placaCruda)) {
                    return response()->json([
                        'estado' => 'Imagen no legible',
                        'mensaje' => 'No se encontró una placa legible. Envía una foto más nítida o escribe la placa.',
                        ...$analisis,
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
                ...$analisis,
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
                ...$analisis,
            ]);
        }

        if ($registrado) {
            return response()->json([
                'estado' => 'Registrada sin paso',
                'placa' => $placa,
                'origen' => $origen,
                'mensaje' => "La placa {$placa} está en el padrón (propietario: {$registrado->propietario}, categoría: {$registrado->categoria}), pero no tiene pasos registrados.",
                'sugerencias' => [],
                ...$analisis,
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
                ...$analisis,
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
            ...$analisis,
        ]);
    }

    private function responderConsultaRapida(string $mensaje, InferenceHealthService $health): string
    {
        $consulta = mb_strtolower($mensaje);
        if (str_contains($consulta, 'tarifa')) {
            return Tarifa::whereIn('categoria_vehiculo', ['moto', 'auto', 'minibus', 'bus', 'camion'])
                ->orderBy('categoria_vehiculo')
                ->orderBy('ejes')
                ->get()
                ->map(fn (Tarifa $tarifa): string => ucfirst($tarifa->categoria_vehiculo)." ({$tarifa->ejes} ejes): Bs. ".
                    number_format((float) $tarifa->monto, 2))
                ->implode("\n") ?: 'No hay tarifas configuradas todavía.';
        }

        if (str_contains($consulta, 'resumen') || str_contains($consulta, 'operativo')) {
            $hoy = now()->toDateString();
            $pasos = Registro::whereDate('created_at', $hoy)->count();
            $recaudacion = DB::table('registros')
                ->join('tarifas', 'registros.tarifa_id', '=', 'tarifas.id')
                ->whereDate('registros.created_at', $hoy)
                ->sum('tarifas.monto');
            $alertas = DB::table('alertas')->where('leida', false)->count();

            return "Resumen de hoy: {$pasos} tránsitos, Bs. ".number_format((float) $recaudacion, 2).
                " recaudados y {$alertas} alerta(s) pendiente(s).";
        }

        if (str_contains($consulta, 'alerta')) {
            $alertas = DB::table('alertas')->where('leida', false)->count();

            return "Hay {$alertas} alerta(s) pendiente(s). Revisa la sección Alertas para atenderlas.";
        }

        if (str_contains($consulta, 'modelo') || str_contains($consulta, 'm1') || str_contains($consulta, 'm4')) {
            return collect($health->status())
                ->map(fn (array $modelo): string => $modelo['nombre'].': '.$modelo['estado'].
                    ($modelo['latencia_ms'] === null ? '' : " ({$modelo['latencia_ms']} ms)"))
                ->implode("\n");
        }

        preg_match('/[A-Z0-9]{6,7}/', strtoupper($mensaje), $coincidencia);
        if (isset($coincidencia[0])) {
            $consulta = Request::create('/agente/consultar', 'POST', ['texto' => $coincidencia[0]]);

            return $this->consultar($consulta)->getData(true)['mensaje'];
        }

        return 'Puedo consultar una placa o ayudarte con tarifas, el resumen operativo, alertas pendientes y el estado de M1–M4. También puedes adjuntar hasta cinco fotografías.';
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
