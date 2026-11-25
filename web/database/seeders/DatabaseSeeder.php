<?php

namespace Database\Seeders;

use App\Models\Tarifa;
use App\Models\Usuario;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Lista blanca de usuarios autorizados (Sin Faker para producción)
        Usuario::firstOrCreate(
            ['email_google' => 'lpze.isaccleonardo.mealla.po@unifranz.edu.bo'], // <-- CAMBIA ESTO POR TU GMAIL REAL
            [
                'nombre' => 'Isacc Mealla (Admin)',
                'rol' => 'admin',
                'activo' => true,
            ]
        );

        // 2. Tarifas base del peaje
        $tarifas = [
            ['categoria_vehiculo' => 'moto', 'monto' => 5.00, 'descripcion' => 'Motocicletas'],
            ['categoria_vehiculo' => 'liviano', 'monto' => 15.00, 'descripcion' => 'Automóviles, vagonetas y camionetas'],
            ['categoria_vehiculo' => 'bus', 'monto' => 30.00, 'descripcion' => 'Buses y transporte público'],
            ['categoria_vehiculo' => 'pesado', 'monto' => 50.00, 'descripcion' => 'Camiones y transporte de carga'],
        ];

        foreach ($tarifas as $tarifa) {
            Tarifa::firstOrCreate(
                ['categoria_vehiculo' => $tarifa['categoria_vehiculo']],
                $tarifa
            );
        }

        $this->call(VehiculoRegistradoSeeder::class);
    }
}
