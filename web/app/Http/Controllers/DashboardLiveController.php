<?php

namespace App\Http\Controllers;

use App\Models\Alerta;
use App\Models\Caseta;
use App\Models\Registro;
use App\Models\VehiculoRegistrado;
use App\Services\InferenceHealthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardLiveController extends Controller
{
    public function __construct(private readonly InferenceHealthService $inferenceHealth) {}

    public function feed(): JsonResponse
    {
        $hoy = now()->toDateString();
        $pasosHoy = Registro::whereDate('created_at', $hoy);
        $registros = Registro::with(['tarifa', 'caseta'])->latest()->take(20)->get();
        $placas = $registros->pluck('placa_detectada')->filter()->unique()->values();
        $vehiculos = VehiculoRegistrado::whereIn('placa', $placas)->get()->keyBy('placa');
        $modelos = $this->inferenceHealth->status();
        $latencias = collect($modelos)->pluck('latencia_ms')->filter(fn ($latencia) => $latencia !== null);
        $transitosPadron = (clone $pasosHoy)->whereIn(
            'placa_detectada',
            VehiculoRegistrado::where('activo', true)->where('saldo', '>', 0)->select('placa')
        )->count();

        return response()->json([
            'kpis' => [
                'recaudacion_dia' => (float) DB::table('registros')
                    ->join('tarifas', 'registros.tarifa_id', '=', 'tarifas.id')
                    ->whereDate('registros.created_at', $hoy)
                    ->sum('tarifas.monto'),
                'total_pasos' => (clone $pasosHoy)->count(),
                'total_acumulado' => Registro::count(),
                'transitos_padron' => $transitosPadron,
                'alertas_activas' => Alerta::where('leida', false)->count(),
                'pasajeros_detectados' => (int) (clone $pasosHoy)->sum('pasajeros'),
            ],
            'infraestructura' => [
                'estado' => collect($modelos)->every(fn (array $modelo) => $modelo['estado'] === 'Operativo')
                    ? 'En Línea'
                    : 'Desconectado',
                'latencia_ms' => $latencias->isEmpty() ? null : round((float) $latencias->max(), 1),
            ],
            'modelos' => $modelos,
            'pasos' => $registros->map(fn (Registro $registro): array => [
                'id' => $registro->id,
                'hora' => $registro->created_at->timezone('America/La_Paz')->format('H:i:s'),
                'captura_url' => route('registros.captura', $registro, false),
                'placa' => $registro->placa_detectada ?: 'No legible',
                'categoria' => $registro->clase_vehiculo ?? 'Desconocido',
                'ejes' => (int) ($registro->ejes ?? 0),
                'pasajeros' => (int) ($registro->pasajeros ?? 0),
                'tarifa' => (float) ($registro->tarifa?->monto ?? 0),
                'confianza' => round((float) $registro->confianza * 100, 1),
                'estado' => $registro->estado,
                'estado_operativo' => $this->estadoOperativo($registro, $vehiculos->get($registro->placa_detectada)),
                'caseta' => $registro->caseta?->nombre,
            ])->values(),
            'alertas' => Alerta::where('leida', false)
                ->latest()
                ->take(10)
                ->get(['id', 'registro_id', 'tipo', 'mensaje', 'created_at'])
                ->map(fn (Alerta $alerta): array => [
                    'id' => $alerta->id,
                    'tipo' => $alerta->tipo,
                    'mensaje' => $alerta->mensaje,
                    'hora' => $alerta->created_at->timezone('America/La_Paz')->format('H:i:s'),
                    'atender_url' => route('alertas.leida', $alerta, false),
                ])->values(),
        ]);
    }

    public function casetaResumen(Caseta $caseta): JsonResponse
    {
        $pasos = Registro::with('tarifa')->where('caseta_id', $caseta->id);

        return response()->json([
            'caseta' => ['id' => $caseta->id, 'nombre' => $caseta->nombre],
            'total_pasos' => (clone $pasos)->count(),
            'pasajeros' => (int) (clone $pasos)->sum('pasajeros'),
            'recaudacion' => (float) DB::table('registros')
                ->join('tarifas', 'registros.tarifa_id', '=', 'tarifas.id')
                ->where('registros.caseta_id', $caseta->id)
                ->sum('tarifas.monto'),
            'pasos_recientes' => $pasos->latest()->take(20)->get(),
        ]);
    }

    private function estadoOperativo(Registro $registro, ?VehiculoRegistrado $vehiculo): string
    {
        if (! $registro->placa_detectada) {
            return 'Placa ilegible';
        }
        if (! $vehiculo || ! $vehiculo->activo) {
            return 'Identificada no registrada';
        }
        if ((float) $vehiculo->saldo <= 0) {
            return 'Sin saldo';
        }

        return 'Registrada y con paso';
    }
}
