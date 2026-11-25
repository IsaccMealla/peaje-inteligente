<?php

namespace Database\Seeders;

use App\Models\VehiculoRegistrado;
use Illuminate\Database\Seeder;

class VehiculoRegistradoSeeder extends Seeder
{
    public function run(): void
    {
        $placas = [
            ['placa' => '4021XYZ', 'propietario' => 'Carlos Mamani', 'categoria' => 'Liviano'],
            ['placa' => '1854ABC', 'propietario' => 'Transporte Andino SRL', 'categoria' => 'Pesado (3+ ejes)'],
            ['placa' => '3090KLM', 'propietario' => 'Ana Quispe', 'categoria' => 'Minibús'],
        ];

        foreach ($placas as $item) {
            VehiculoRegistrado::updateOrCreate(['placa' => $item['placa']], $item);
        }
    }
}
