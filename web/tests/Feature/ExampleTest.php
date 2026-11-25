<?php

namespace Tests\Feature;

use App\Models\Caseta;
use App\Models\Registro;
use App\Models\Tarifa;
use App\Models\Usuario;
use App\Models\VehiculoRegistrado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
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
            ->assertSee('Agente Conversacional')
            ->assertSee('feedUrl')
            ->assertSee('tabla-registros');
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
                'pasajeros' => 3,
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
            ->assertJsonPath('registro.pasajeros', 3)
            ->assertJsonPath('registro.clase_vehiculo', 'Pesado (3+ ejes)')
            ->assertJsonPath('registro.tarifa.categoria_vehiculo', 'Pesado (3+ ejes)')
            ->assertJsonPath('registro.tarifa.monto', 75);

        $imagenDataUri = $response->json('registro.imagen_url');
        $this->assertStringStartsWith('data:image/jpeg;base64,', $imagenDataUri);
        $jpeg = base64_decode(substr($imagenDataUri, strlen('data:image/jpeg;base64,')), true);
        $this->assertNotFalse($jpeg);
        $dimensiones = getimagesizefromstring($jpeg);
        $this->assertLessThanOrEqual(480, $dimensiones[0]);
        $this->assertSame([], Storage::disk('public')->allFiles('capturas'));

        Http::assertSent(fn ($request) => str_contains($request->body(), 'name="imagen"'));
        $this->assertDatabaseHas('alertas', [
            'registro_id' => $response->json('registro.id'),
            'tipo' => 'vehiculo_no_registrado',
        ]);
    }

    public function test_zero_balance_vehicle_creates_evasion_alert_and_versioned_routes_are_present(): void
    {
        Storage::fake('public');
        Http::fake([
            'http://localhost:8000/inferir' => Http::response([
                'placa' => '9002QAB',
                'confianza' => 0.94,
                'categoria' => 'auto',
                'ejes' => 2,
                'pasajeros' => 1,
            ]),
        ]);
        $usuario = Usuario::create([
            'email_google' => 'caseta-operador@example.com',
            'nombre' => 'Operador',
            'rol' => 'operador',
            'activo' => true,
        ]);
        VehiculoRegistrado::create([
            'placa' => '9002QAB',
            'propietario' => 'Titular demostración',
            'categoria' => 'Liviano',
            'saldo' => 0,
            'activo' => true,
        ]);

        $response = $this->actingAs($usuario, 'sanctum')
            ->postJson('/api/v1/pasos/frame', [
                'imagen' => UploadedFile::fake()->image('vehiculo.jpg'),
            ]);

        $response->assertOk()
            ->assertJsonPath('paso.pasajeros', 1)
            ->assertJsonPath('paso.estado', 'revision');
        $this->assertDatabaseHas('alertas', [
            'registro_id' => $response->json('paso.id'),
            'tipo' => 'saldo_insuficiente',
        ]);

        foreach ([
            'api/v1/auth/google',
            'api/v1/pasos/frame',
            'api/v1/placas/consultar',
            'api/v1/placas/consultar-imagen',
            'api/v1/placas/{placa}/historial',
            'api/v1/casetas/{caseta}/resumen',
            'api/v1/agente/chat',
            'api/v1/dashboard/live',
        ] as $uri) {
            $this->assertTrue(
                collect(app('router')->getRoutes()->getRoutes())
                    ->contains(fn ($registered) => $registered->uri() === $uri),
                "Missing required API route: {$uri}"
            );
        }

        $collection = json_decode(
            file_get_contents(base_path('../docs/postman_collection.json')),
            true,
            flags: JSON_THROW_ON_ERROR
        );
        $this->assertCount(8, $collection['item']);
    }

    public function test_seeder_creates_admin_caseta_and_at_least_twenty_one_synthetic_vehicles(): void
    {
        $this->seed();

        $this->assertDatabaseHas('usuarios', [
            'email_google' => 'doc.alvaro.pinto.ma@unifranz.edu.bo',
            'rol' => 'admin',
            'activo' => true,
        ]);
        $this->assertDatabaseHas('casetas', ['nombre' => 'Caseta principal']);
        $this->assertGreaterThanOrEqual(21, VehiculoRegistrado::count());
        $this->assertGreaterThan(0, VehiculoRegistrado::where('saldo', '>', 0)->count());
        $this->assertGreaterThan(0, VehiculoRegistrado::where('saldo', '<=', 0)->count());
    }

    public function test_live_dashboard_returns_kpis_and_four_model_states(): void
    {
        Cache::flush();
        Http::fake([
            'http://localhost:8000/health' => Http::response([
                'status' => 'ok',
                'm1_placa' => 'plates.onnx',
                'm1_reconocimiento' => 'ocr.onnx',
                'm2_vehiculos' => 'vehicles.pt',
                'm3_llantas' => 'wheels.pt',
                'm4_pasajeros' => 'passengers.pt',
                'latencia_modelos' => ['m1' => 5, 'm2' => 6, 'm3' => 7, 'm4' => 8],
            ]),
        ]);
        $usuario = Usuario::create([
            'email_google' => 'dashboard-operador@example.com',
            'nombre' => 'Operador',
            'rol' => 'operador',
            'activo' => true,
        ]);
        $caseta = Caseta::create(['nombre' => 'Caseta de prueba', 'activa' => true]);
        $tarifa = Tarifa::create(['categoria_vehiculo' => 'liviano', 'monto' => 15]);
        Registro::create([
            'usuario_id' => $usuario->id,
            'tarifa_id' => $tarifa->id,
            'caseta_id' => $caseta->id,
            'placa_detectada' => '9001QAA',
            'clase_vehiculo' => 'Liviano',
            'ejes' => 2,
            'pasajeros' => 2,
            'confianza' => 0.9,
            'imagen_url' => 'data:image/jpeg;base64,',
            'estado' => 'procesado',
        ]);

        $response = $this->actingAs($usuario, 'sanctum')
            ->getJson('/api/v1/dashboard/live')
            ->assertOk()
            ->assertJsonPath('kpis.total_pasos', 1)
            ->assertJsonPath('kpis.pasajeros_detectados', 2)
            ->assertJsonPath('modelos.m4.estado', 'Operativo')
            ->assertJsonPath('modelos.m4.latencia_ms', 8);

        $this->assertCount(4, $response->json('modelos'));
        $this->actingAs($usuario, 'sanctum')
            ->getJson('/api/v1/casetas/'.$caseta->id.'/resumen')
            ->assertOk()
            ->assertJsonPath('caseta.nombre', 'Caseta de prueba')
            ->assertJsonPath('total_pasos', 1);
        $this->actingAs($usuario, 'sanctum')
            ->getJson('/api/v1/placas/9001QAA/historial')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('placa', '9001QAA');
    }

    public function test_backoffice_pages_and_vehicle_crud_are_available(): void
    {
        $usuario = Usuario::create([
            'email_google' => 'backoffice@example.com',
            'nombre' => 'Operador',
            'rol' => 'admin',
            'activo' => true,
        ]);
        Http::fake([
            'http://localhost:8000/health' => Http::response([
                'm1_placa' => 'plates.onnx',
                'm1_reconocimiento' => 'ocr.onnx',
                'm2_vehiculos' => 'vehicles.pt',
                'm3_llantas' => 'wheels.pt',
                'm4_pasajeros' => 'passengers.pt',
            ]),
        ]);
        Cache::flush();

        $this->actingAs($usuario)
            ->get('/vehiculos')
            ->assertOk()
            ->assertSee('Vehículos registrados');
        $this->post('/vehiculos', [
            'placa' => '9001QAA',
            'propietario' => 'Titular de prueba',
            'categoria' => 'Liviano',
            'saldo' => 80,
            'activo' => 1,
        ])->assertRedirect();
        $vehiculo = VehiculoRegistrado::where('placa', '9001QAA')->firstOrFail();
        $this->put('/vehiculos/'.$vehiculo->id, [
            'placa' => '9001QAA',
            'propietario' => 'Titular actualizado',
            'categoria' => 'Bus',
            'saldo' => 0,
            'activo' => 1,
        ])->assertRedirect();
        $this->assertDatabaseHas('vehiculo_registrados', [
            'id' => $vehiculo->id,
            'categoria' => 'Bus',
            'saldo' => 0,
        ]);

        $this->get('/pasos')->assertOk()->assertSee('Historial de pasos');
        $this->get('/alertas')->assertOk()->assertSee('Alertas');
        $this->get('/modelos')->assertOk()->assertSee('Estado de modelos IA');
        $this->get('/dashboard/feed')->assertOk()->assertJsonStructure([
            'kpis',
            'modelos' => ['m1', 'm2', 'm3', 'm4'],
            'pasos',
            'alertas',
        ]);
    }

    public function test_dashboard_capture_endpoint_serves_image_saved_in_database(): void
    {
        $usuario = Usuario::create([
            'email_google' => 'captura@example.com',
            'nombre' => 'Operador',
            'rol' => 'operador',
            'activo' => true,
        ]);
        $imagen = imagecreatetruecolor(32, 24);
        ob_start();
        imagejpeg($imagen);
        $jpeg = ob_get_clean();
        imagedestroy($imagen);

        $registro = Registro::create([
            'usuario_id' => $usuario->id,
            'placa_detectada' => '1234ABC',
            'imagen_url' => 'data:image/jpeg;base64,'.base64_encode($jpeg),
            'estado' => 'revision',
        ]);

        $this->actingAs($usuario)
            ->get(route('registros.captura', $registro))
            ->assertOk()
            ->assertHeader('content-type', 'image/jpeg')
            ->assertContent($jpeg);
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
