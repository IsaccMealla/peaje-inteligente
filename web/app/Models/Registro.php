<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Registro extends Model
{
    protected $guarded = [];

    public function tarifa()
    {
        return $this->belongsTo(Tarifa::class);
    }
}
