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
        'pasajeros',
        'confianza',
        'latencia_ms',
        'imagen_url',
        'estado',
        'caseta_id',
    ];

    public function tarifa()
    {
        return $this->belongsTo(Tarifa::class);
    }

    public function caseta()
    {
        return $this->belongsTo(Caseta::class);
    }
}
