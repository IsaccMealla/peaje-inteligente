<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class InferenceHealthService
{
    public function status(): array
    {
        return Cache::remember('inference.health.status', now()->addSeconds(3), function (): array {
            $baseUrl = rtrim((string) config('services.ia.url'), '/');
            $keys = [
                'm1' => ['M1: Detección y OCR de placas', 'm1_placa', 'm1_reconocimiento'],
                'm2' => ['M2: Segmentación de vehículos', 'm2_vehiculos', null],
                'm3' => ['M3: Keypoints de ruedas', 'm3_llantas', null],
                'm4' => ['M4: Segmentación de pasajeros', 'm4_pasajeros', null],
            ];

            if ($baseUrl === '') {
                return $this->desconectados($keys, 'INFERENCE_URL no está configurada.');
            }

            try {
                $inicio = microtime(true);
                $response = Http::timeout(2)->get($baseUrl.'/health');
                $latencia = round((microtime(true) - $inicio) * 1000, 1);
            } catch (ConnectionException $exception) {
                report($exception);

                return $this->desconectados($keys, 'No se pudo conectar con el servicio de inferencia.');
            }

            if (! $response->successful()) {
                return $this->desconectados($keys, "Inferencia respondió HTTP {$response->status()}.");
            }

            $datos = $response->json();
            $latencias = $datos['latencia_modelos'] ?? [];
            $modelos = [];
            foreach ($keys as $id => [$nombre, $clave, $claveAlterna]) {
                $operativo = ! empty($datos[$clave])
                    && ($id !== 'm1' || ! empty($datos[$claveAlterna]));
                $modelos[$id] = [
                    'nombre' => $nombre,
                    'estado' => $operativo ? 'Operativo' : 'Sin conexión',
                    'latencia_ms' => isset($latencias[$id])
                        ? round((float) $latencias[$id], 1)
                        : ($operativo ? $latencia : null),
                ];
            }

            return $modelos;
        });
    }

    private function desconectados(array $keys, string $mensaje): array
    {
        $modelos = [];
        foreach ($keys as $id => [$nombre]) {
            $modelos[$id] = [
                'nombre' => $nombre,
                'estado' => 'Sin conexión',
                'latencia_ms' => null,
                'mensaje' => $mensaje,
            ];
        }

        return $modelos;
    }
}
