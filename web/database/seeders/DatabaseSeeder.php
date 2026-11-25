<?php

namespace Database\Seeders;

use App\Models\Caseta;
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
        Usuario::updateOrCreate(
            ['email_google' => 'doc.alvaro.pinto.ma@unifranz.edu.bo'],
            [
                'nombre' => 'Ing. Alvaro Pinto (Docente)',
                'rol' => 'admin',
                'activo' => true,
            ]
        );

        // 2. Tarifas base del peaje
        $tarifas = [
            ['categoria_vehiculo' => 'moto', 'ejes' => 1, 'monto' => 5.00, 'descripcion' => 'Motocicletas'],
            ['categoria_vehiculo' => 'auto', 'ejes' => 2, 'monto' => 15.00, 'descripcion' => 'Automóviles, vagonetas y camionetas'],
            ['categoria_vehiculo' => 'minibus', 'ejes' => 2, 'monto' => 15.00, 'descripcion' => 'Minibuses'],
            ['categoria_vehiculo' => 'bus', 'ejes' => 2, 'monto' => 30.00, 'descripcion' => 'Buses y transporte público'],
            ['categoria_vehiculo' => 'camion', 'ejes' => 2, 'monto' => 50.00, 'descripcion' => 'Camiones de dos ejes'],
        ];

        foreach ($tarifas as $tarifa) {
            Tarifa::updateOrCreate(
                [
                    'categoria_vehiculo' => $tarifa['categoria_vehiculo'],
                    'ejes' => $tarifa['ejes'],
                ],
                ['monto' => $tarifa['monto'], 'descripcion' => $tarifa['descripcion']]
            );
        }

        Caseta::updateOrCreate(
            ['nombre' => 'Caseta principal'],
            ['ubicacion' => 'Ubicación principal', 'activa' => true]
        );

        $this->call(VehiculoRegistradoSeeder::class);
    }
}
