<?php

namespace Tests\Feature;

use App\Models\Registro;
use App\Models\Tarifa;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
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
            ->assertSee('Agente conversacional')
            ->assertSee('agente\\/consultar', false)
            ->assertSee('1234ABC');
    }

    public function test_processing_saves_axles_latency_and_mapped_tariff(): void
    {
        Storage::fake('public');
        Http::fake([
            'http://localhost:8000/inferir' => Http::response([
                'placa' => '1234ABC',
                'confianza' => 0.95,
                'categoria' => 'camion',
                'ejes' => 4,
                'latencia' => 128.5,
            ]),
        ]);

        $usuario = Usuario::create([
            'email_google' => 'operador@example.com',
            'nombre' => 'Operador',
            'rol' => 'operador',
            'activo' => true,
        ]);

        $response = $this->actingAs($usuario, 'sanctum')
            ->postJson('/api/procesar', [
                'imagen' => UploadedFile::fake()->image('vehiculo.jpg'),
            ]);

        $response->assertOk()
            ->assertJsonPath('registro.ejes', 4)
            ->assertJsonPath('registro.latencia_ms', 128.5)
            ->assertJsonPath('registro.clase_vehiculo', 'Pesado (3+ ejes)')
            ->assertJsonPath('registro.tarifa.categoria_vehiculo', 'Pesado (3+ ejes)')
            ->assertJsonPath('registro.tarifa.monto', 75);

        Http::assertSent(fn ($request) => str_contains($request->body(), 'name="imagen"'));
    }

    public function test_bus_uses_the_seeded_bus_tariff(): void
    {
        Storage::fake('public');
        Http::fake([
            'http://localhost:8000/inferir' => Http::response([
                'placa' => '1234ABC',
                'confianza' => 0.95,
                'categoria' => 'bus',
                'ejes' => 2,
                'latencia' => 128.5,
            ]),
        ]);

        $usuario = Usuario::create([
            'email_google' => 'bus-operador@example.com',
            'nombre' => 'Operador',
            'rol' => 'operador',
            'activo' => true,
        ]);
        Tarifa::create([
            'categoria_vehiculo' => 'bus',
            'monto' => 30,
            'descripcion' => 'Buses y transporte público',
        ]);

        $this->actingAs($usuario, 'sanctum')
            ->postJson('/api/procesar', [
                'imagen' => UploadedFile::fake()->image('bus.jpg'),
            ])
            ->assertOk()
            ->assertJsonPath('registro.clase_vehiculo', 'Bus')
            ->assertJsonPath('registro.tarifa.categoria_vehiculo', 'bus')
            ->assertJsonPath('registro.tarifa.monto', 30);
    }
}
