<?php

namespace App\Events;

use App\Models\Registro;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NuevoRegistroPeaje implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $datos;

    public function __construct(Registro $registro)
    {
        $registro->load('tarifa');
        $this->datos = [
            'id' => $registro->id,
            'placa' => $registro->placa_detectada ?? 'No legible',
            'categoria' => $registro->clase_vehiculo ?? 'Desconocido',
            'tarifa' => $registro->tarifa ? $registro->tarifa->monto : 0,
            'confianza' => round($registro->confianza * 100, 1),
            'imagen_url' => route('registros.captura', ['registro' => $registro->id], false),
            'estado' => $registro->estado,
            'hora' => $registro->created_at->format('H:i:s'),
        ];
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('peaje-canal'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'nuevo-registro';
    }
}
