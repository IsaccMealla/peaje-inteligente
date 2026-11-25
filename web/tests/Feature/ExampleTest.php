<?php

namespace Tests\Feature;

use App\Models\Registro;
use App\Models\Tarifa;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function test_dashboard_loads_tarifa_relationship(): void
    {
        $usuario = Usuario::create([
            'email_google' => 'operador@example.com',
            'nombre' => 'Operador',
            'rol' => 'operador',
            'activo' => true,
        ]);

        $tarifa = Tarifa::create([
            'categoria_vehiculo' => 'liviano',
            'monto' => 15,
            'descripcion' => 'Automóviles',
        ]);

        Registro::create([
            'usuario_id' => $usuario->id,
            'tarifa_id' => $tarifa->id,
            'placa_detectada' => '1234ABC',
            'clase_vehiculo' => 'liviano',
            'confianza' => 0.95,
            'imagen_url' => 'capturas/test.jpg',
            'estado' => 'procesado',
        ]);

        $this->actingAs($usuario)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('1234ABC');
    }
}
