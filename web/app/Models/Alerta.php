<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Alerta extends Model
{
    protected $guarded = [];

    public function registro()
    {
        return $this->belongsTo(Registro::class);
    }
}
