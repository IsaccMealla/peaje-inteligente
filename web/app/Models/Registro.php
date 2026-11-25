<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Registro extends Model
{
    protected $fillable = [
        'usuario_id',
        'tarifa_id',
        'placa_detectada',
        'clase_vehiculo',
        'ejes',
        'confianza',
        'latencia_ms',
        'imagen_url',
        'estado',
    ];

    public function tarifa()
    {
        return $this->belongsTo(Tarifa::class);
    }
}
