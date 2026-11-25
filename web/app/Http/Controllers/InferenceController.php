<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use App\Models\VehiculoRegistrado;
use App\Services\InferenceHealthService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;

class InferenceController extends Controller
{
    public function index(InferenceHealthService $health): View
    {
        return view('estado-inferencia', [
            'modelos' => $health->status(),
            'inferenceUrl' => (string) config('services.ia.url'),
            'vehiculosTotal' => VehiculoRegistrado::count(),
            'usuariosTotal' => Usuario::count(),
        ]);
    }

    public function comprobar(InferenceHealthService $health): JsonResponse
    {
        Cache::forget('inference.health.status');
        $modelos = $health->status();
        $conectados = collect($modelos)->filter(fn (array $modelo) => $modelo['estado'] === 'Operativo')->count();

        return response()->json([
            'estado' => $conectados === count($modelos) ? 'En Línea' : 'Desconectado',
            'modelos' => $modelos,
            'url' => (string) config('services.ia.url'),
        ]);
    }

    public function analizar(Request $request): JsonResponse
    {
        $request->validate([
            'imagen' => ['required', 'image', 'max:10240'],
        ]);

        $baseUrl = rtrim((string) config('services.ia.url'), '/');
        if ($baseUrl === '') {
            return response()->json(['mensaje' => 'INFERENCE_URL no está configurada.'], 503);
        }

        $imagen = $request->file('imagen');
        try {
            $inicio = microtime(true);
            $respuesta = Http::timeout(30)
                ->attach('imagen', file_get_contents($imagen->getRealPath()), $imagen->getClientOriginalName())
                ->post($baseUrl.'/inferir');
        } catch (ConnectionException $exception) {
            report($exception);

            return response()->json(['mensaje' => 'No se pudo conectar con el servicio de inferencia.'], 502);
        }

        if (! $respuesta->successful()) {
            return response()->json([
                'mensaje' => 'El servicio de inferencia respondió HTTP '.$respuesta->status().'.',
            ], 502);
        }

        return response()->json([
            'resultado' => $respuesta->json(),
            'latencia_solicitud_ms' => round((microtime(true) - $inicio) * 1000, 1),
        ]);
    }
}
