<?php

namespace Database\Seeders;

use App\Models\VehiculoRegistrado;
use Illuminate\Database\Seeder;

class VehiculoRegistradoSeeder extends Seeder
{
    public function run(): void
    {
        $placas = [
            ['placa' => '9001QAA', 'propietario' => 'Titular demostración 01', 'categoria' => 'Liviano', 'saldo' => 100],
            ['placa' => '9002QAB', 'propietario' => 'Titular demostración 02', 'categoria' => 'Liviano', 'saldo' => 0],
            ['placa' => '9003QAC', 'propietario' => 'Titular demostración 03', 'categoria' => 'Motocicleta', 'saldo' => 50],
            ['placa' => '9004QAD', 'propietario' => 'Titular demostración 04', 'categoria' => 'Minibús', 'saldo' => 0],
            ['placa' => '9005QAE', 'propietario' => 'Titular demostración 05', 'categoria' => 'Bus', 'saldo' => 250],
            ['placa' => '9006QAF', 'propietario' => 'Titular demostración 06', 'categoria' => 'Liviano', 'saldo' => 75],
            ['placa' => '9007QAG', 'propietario' => 'Titular demostración 07', 'categoria' => 'Pesado', 'saldo' => 0],
            ['placa' => '9008QAH', 'propietario' => 'Titular demostración 08', 'categoria' => 'Liviano', 'saldo' => 40],
            ['placa' => '9009QAJ', 'propietario' => 'Titular demostración 09', 'categoria' => 'Minibús', 'saldo' => 120],
            ['placa' => '9010QAK', 'propietario' => 'Titular demostración 10', 'categoria' => 'Liviano', 'saldo' => 0],
            ['placa' => '9011QAL', 'propietario' => 'Titular demostración 11', 'categoria' => 'Bus', 'saldo' => 90],
            ['placa' => '9012QAM', 'propietario' => 'Titular demostración 12', 'categoria' => 'Liviano', 'saldo' => 10],
            ['placa' => '9013QAN', 'propietario' => 'Titular demostración 13', 'categoria' => 'Pesado', 'saldo' => 0],
            ['placa' => '9014QAP', 'propietario' => 'Titular demostración 14', 'categoria' => 'Liviano', 'saldo' => 300],
            ['placa' => '9015QAQ', 'propietario' => 'Titular demostración 15', 'categoria' => 'Motocicleta', 'saldo' => 0],
            ['placa' => '9016QAR', 'propietario' => 'Titular demostración 16', 'categoria' => 'Minibús', 'saldo' => 35],
            ['placa' => '9017QAS', 'propietario' => 'Titular demostración 17', 'categoria' => 'Liviano', 'saldo' => 0],
            ['placa' => '9018QAT', 'propietario' => 'Titular demostración 18', 'categoria' => 'Bus', 'saldo' => 180],
            ['placa' => '9019QAU', 'propietario' => 'Titular demostración 19', 'categoria' => 'Liviano', 'saldo' => 65],
            ['placa' => '9020QAV', 'propietario' => 'Titular demostración 20', 'categoria' => 'Pesado', 'saldo' => 0],
            ['placa' => '9021QAW', 'propietario' => 'Titular demostración 21', 'categoria' => 'Liviano', 'saldo' => 200],
        ];

        foreach ($placas as $item) {
            VehiculoRegistrado::updateOrCreate(['placa' => $item['placa']], $item);
        }
    }
}
