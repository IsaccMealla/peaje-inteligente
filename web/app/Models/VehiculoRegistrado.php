<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehiculoRegistrado extends Model
{
    protected $table = 'vehiculo_registrados';

    protected $fillable = [
        'placa',
        'propietario',
        'categoria',
        'activo',
    ];
}
