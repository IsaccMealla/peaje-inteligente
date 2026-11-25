<?php

namespace Tests\Feature;

use App\Models\Alerta;
use App\Models\Caseta;
use App\Models\Registro;
use App\Models\Tarifa;
use App\Models\Usuario;
use App\Models\VehiculoRegistrado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OperationsModulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_tariff_and_booth_crud_is_limited_to_admins(): void
    {
        $admin = $this->createUser('admin', 'admin@example.com');
        $this->actingAs($admin);

        $this->post('/tarifas-casetas/tarifas', [
            'categoria_vehiculo' => 'camion',
            'ejes' => 3,
            'monto' => 72.5,
            'descripcion' => 'Camión de tres ejes',
        ])->assertRedirect();
        $tarifa = Tarifa::where('categoria_vehiculo', 'camion')->where('ejes', 3)->firstOrFail();
        $this->put("/tarifas-casetas/tarifas/{$tarifa->id}", [
            'categoria_vehiculo' => 'camion',
            'ejes' => 3,
            'monto' => 75,
            'descripcion' => 'Actualizada',
        ])->assertRedirect();
        $this->assertDatabaseHas('tarifas', ['id' => $tarifa->id, 'monto' => 75]);

        $this->post('/tarifas-casetas/casetas', [
            'nombre' => 'Caseta Norte',
            'ubicacion' => 'Ruta Norte, km 5',
        ])->assertRedirect();
        $caseta = Caseta::where('nombre', 'Caseta Norte')->firstOrFail();
        $this->put("/tarifas-casetas/casetas/{$caseta->id}", [
            'nombre' => 'Caseta Norte 2',
            'ubicacion' => 'Ruta Norte, km 6',
            'activa' => 1,
        ])->assertRedirect();
        $this->assertDatabaseHas('casetas', [
            'id' => $caseta->id,
            'nombre' => 'Caseta Norte 2',
            'ubicacion' => 'Ruta Norte, km 6',
        ]);
        $this->delete("/tarifas-casetas/casetas/{$caseta->id}")->assertRedirect();
        $this->delete("/tarifas-casetas/tarifas/{$tarifa->id}")->assertRedirect();

        $operador = $this->createUser('operador', 'operador@example.com');
        $this->actingAs($operador)
            ->post('/tarifas-casetas/tarifas', [
                'categoria_vehiculo' => 'auto',
                'ejes' => 2,
                'monto' => 20,
            ])->assertForbidden();
        $this->get('/tarifas-casetas')->assertOk();
    }

    public function test_inference_uses_tariff_matching_both_vehicle_category_and_axles(): void
    {
        Http::fake([
            'http://localhost:8000/inferir' => Http::response([
                'placa' => '4021XYZ',
                'confianza' => 0.96,
                'categoria' => 'camion',
                'ejes' => 3,
                'pasajeros' => 2,
            ]),
        ]);
        $usuario = $this->createUser('operador', 'operador@example.com');
        VehiculoRegistrado::create([
            'placa' => '4021XYZ',
            'propietario' => 'Prueba',
            'categoria' => 'Pesado',
            'saldo' => 100,
            'activo' => true,
        ]);
        Tarifa::create(['categoria_vehiculo' => 'camion', 'ejes' => 3, 'monto' => 72.5]);

        $this->actingAs($usuario, 'sanctum')
            ->postJson('/api/v1/pasos/frame', ['imagen' => UploadedFile::fake()->image('camion.jpg')])
            ->assertOk()
            ->assertJsonPath('paso.tarifa.monto', 72.5);
    }

    public function test_unavailable_inference_does_not_report_or_charge_an_auto(): void
    {
        Http::fake([
            'http://localhost:8000/inferir' => Http::response([], 503),
        ]);
        Tarifa::create(['categoria_vehiculo' => 'auto', 'ejes' => 2, 'monto' => 15]);
        $usuario = $this->createUser('operador', 'inference-offline@example.com');

        $this->actingAs($usuario, 'sanctum')
            ->postJson('/api/v1/pasos/frame', [
                'imagen' => UploadedFile::fake()->image('sin-inferencia.jpg'),
            ])
            ->assertOk()
            ->assertJsonPath('inferencia_disponible', false)
            ->assertJsonPath('paso.clase_vehiculo', 'Sin clasificar')
            ->assertJsonPath('paso.tarifa', null);

        $this->assertDatabaseHas('registros', [
            'clase_vehiculo' => 'Sin clasificar',
            'tarifa_id' => null,
            'estado' => 'revision',
        ]);
    }

    public function test_detected_auto_is_reported_as_auto(): void
    {
        Http::fake([
            'http://localhost:8000/inferir' => Http::response([
                'placa' => '5571SIS',
                'confianza' => 0.96,
                'confianza_vehiculo' => 0.78,
                'categoria' => 'auto',
                'ejes' => 2,
                'pasajeros' => 0,
            ]),
        ]);
        Tarifa::create(['categoria_vehiculo' => 'auto', 'ejes' => 2, 'monto' => 15]);
        $usuario = $this->createUser('operador', 'auto-category@example.com');

        $this->actingAs($usuario, 'sanctum')
            ->postJson('/api/v1/pasos/frame', [
                'imagen' => UploadedFile::fake()->image('auto.jpg'),
            ])
            ->assertOk()
            ->assertJsonPath('paso.clase_vehiculo', 'Auto')
            ->assertJsonPath('paso.tarifa.monto', 15);
    }

    public function test_web_agent_handles_quick_queries_and_multiple_photos(): void
    {
        Http::fake([
            'http://localhost:8000/inferir' => Http::response([
                'placa' => '4021XYZ',
                'confianza' => 0.96,
                'confianza_ocr' => 0.96,
                'categoria' => 'auto',
                'ejes' => 2,
                'pasajeros' => 1,
                'latencia' => 145,
                'latencia_modelos' => ['m1' => 45, 'm2' => 35, 'm3' => 40, 'm4' => 25],
            ]),
        ]);
        $usuario = $this->createUser('operador', 'operador@example.com');
        Tarifa::create(['categoria_vehiculo' => 'auto', 'ejes' => 2, 'monto' => 15]);
        VehiculoRegistrado::create([
            'placa' => '4021XYZ',
            'propietario' => 'Prueba',
            'categoria' => 'Liviano',
            'saldo' => 50,
            'activo' => true,
        ]);

        $this->actingAs($usuario)
            ->postJson('/agente/chat', ['mensaje' => 'Tarifas del peaje'])
            ->assertOk()
            ->assertJsonPath('respuesta', 'Auto (2 ejes): Bs. 15.00');

        $this->post('/agente/chat', [
            'mensaje' => 'Revisa estas capturas',
            'imagenes' => [
                UploadedFile::fake()->image('auto-1.jpg'),
                UploadedFile::fake()->image('auto-2.jpg'),
            ],
        ])->assertOk()
            ->assertJsonCount(2, 'resultados')
            ->assertJsonPath('resultados.0.estado', 'Registrada sin paso')
            ->assertJsonPath('resultados.0.analisis.pasajeros', 1);
        $this->assertCount(4, session('agente_historial'));
    }

    public function test_inference_page_can_run_a_manual_photo_analysis(): void
    {
        Http::fake([
            'http://localhost:8000/inferir' => Http::response([
                'placa' => '4021XYZ',
                'confianza_ocr' => 0.94,
                'categoria' => 'auto',
                'ejes' => 2,
                'pasajeros' => 2,
                'latencia' => 125,
                'latencia_modelos' => ['m1' => 40, 'm2' => 30, 'm3' => 35, 'm4' => 20],
            ]),
        ]);
        $usuario = $this->createUser('operador', 'inference@example.com');

        $this->actingAs($usuario)
            ->postJson('/estado-inferencia/analizar', [
                'imagen' => UploadedFile::fake()->image('prueba.jpg'),
            ])
            ->assertOk()
            ->assertJsonPath('resultado.placa', '4021XYZ')
            ->assertJsonPath('resultado.confianza_ocr', 0.94)
            ->assertJsonPath('resultado.pasajeros', 2);
    }

    public function test_dashboard_alert_can_be_marked_attended_with_ajax(): void
    {
        $usuario = $this->createUser('operador', 'alerts@example.com');
        $alerta = Alerta::create([
            'tipo' => 'saldo_insuficiente',
            'mensaje' => 'Saldo insuficiente',
            'leida' => false,
        ]);

        $this->actingAs($usuario)
            ->patchJson("/alertas/{$alerta->id}/leida")
            ->assertOk()
            ->assertJsonPath('id', $alerta->id)
            ->assertJsonPath('leida', true);
        $this->assertDatabaseHas('alertas', ['id' => $alerta->id, 'leida' => true]);
    }

    public function test_history_and_alert_pages_apply_their_filters(): void
    {
        $usuario = $this->createUser('operador', 'filters@example.com');
        Registro::create([
            'usuario_id' => $usuario->id,
            'placa_detectada' => '4021XYZ',
            'imagen_url' => 'data:image/jpeg;base64,',
            'estado' => 'revision',
        ]);
        Alerta::create([
            'tipo' => 'saldo_insuficiente',
            'mensaje' => 'Saldo insuficiente',
            'leida' => false,
        ]);
        Alerta::create([
            'tipo' => 'vehiculo_no_registrado',
            'mensaje' => 'Atendida de prueba',
            'leida' => true,
        ]);

        $this->actingAs($usuario)
            ->get('/pasos?placa=4021-xyz&hasta='.now()->toDateString())
            ->assertOk()
            ->assertSee('4021XYZ');
        $this->get('/alertas?estado=pendientes')
            ->assertOk()
            ->assertSee('SIN_SALDO')
            ->assertSee('Pendiente')
            ->assertDontSee('Atendida de prueba');
        $this->get('/alertas?estado=atendidas')
            ->assertOk()
            ->assertSee('Atendida de prueba')
            ->assertSee('Atendida');
    }

    private function createUser(string $rol, string $correo): Usuario
    {
        return Usuario::create([
            'email_google' => $correo,
            'nombre' => ucfirst($rol),
            'rol' => $rol,
            'activo' => true,
        ]);
    }
}
