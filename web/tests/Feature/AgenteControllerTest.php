<?php

namespace Tests\Feature;

use App\Models\Registro;
use App\Models\Tarifa;
use App\Models\Usuario;
use App\Models\VehiculoRegistrado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AgenteControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_requires_authentication(): void
    {
        $this->postJson('/api/agente/consultar', ['texto' => '4021XYZ'])
            ->assertUnauthorized();
    }

    public function test_registered_vehicle_with_pass_reports_database_details(): void
    {
        $usuario = $this->crearUsuario();
        $vehiculo = VehiculoRegistrado::create([
            'placa' => '4021XYZ',
            'propietario' => 'Carlos Mamani',
            'categoria' => 'Liviano',
        ]);
        $tarifa = Tarifa::create([
            'categoria_vehiculo' => 'liviano',
            'monto' => 15,
        ]);
        $this->crearRegistro($vehiculo->placa, $usuario, $tarifa);

        $this->actingAs($usuario)
            ->postJson('/agente/consultar', ['texto' => '4021-xyz'])
            ->assertOk()
            ->assertJsonPath('estado', 'Registrada y con paso')
            ->assertJsonPath('placa', '4021XYZ')
            ->assertJsonPath('sugerencias', [])
            ->assertSeeText('Carlos Mamani')
            ->assertSeeText('liviano')
            ->assertSeeText('Bs. 15.00');
    }

    public function test_registered_vehicle_without_pass_is_reported(): void
    {
        $usuario = $this->crearUsuario();
        VehiculoRegistrado::create([
            'placa' => '4021XYZ',
            'propietario' => 'Carlos Mamani',
            'categoria' => 'Liviano',
        ]);

        $this->actingAs($usuario)
            ->postJson('/agente/consultar', ['texto' => '4021XYZ'])
            ->assertOk()
            ->assertJsonPath('estado', 'Registrada sin paso')
            ->assertSeeText('no tiene pasos registrados');
    }

    public function test_detected_plate_not_in_registry_is_identified(): void
    {
        $usuario = $this->crearUsuario();
        $this->crearRegistro('4021XYZ', $usuario);

        $this->actingAs($usuario)
            ->postJson('/agente/consultar', ['texto' => '4021XYZ'])
            ->assertOk()
            ->assertJsonPath('estado', 'Identificada no registrada');
    }

    public function test_unknown_plate_suggests_ocr_correction(): void
    {
        $usuario = $this->crearUsuario();
        VehiculoRegistrado::create([
            'placa' => '4021XYZ',
            'propietario' => 'Carlos Mamani',
            'categoria' => 'Liviano',
        ]);

        $this->actingAs($usuario)
            ->postJson('/agente/consultar', ['texto' => '4O21XYZ'])
            ->assertOk()
            ->assertJsonPath('estado', 'No existe')
            ->assertJsonPath('sugerencias.0', '4021XYZ')
            ->assertJsonPath(
                'mensaje',
                'La placa 4O21XYZ no aparece en el padrón ni en los registros de paso. ¿Quisiste decir 4021XYZ?'
            );
    }

    public function test_image_input_uses_inference_service_to_get_plate(): void
    {
        Http::fake([
            'http://localhost:8000/inferir' => Http::response(['placa' => '4021XYZ']),
        ]);
        $usuario = $this->crearUsuario();
        VehiculoRegistrado::create([
            'placa' => '4021XYZ',
            'propietario' => 'Carlos Mamani',
            'categoria' => 'Liviano',
        ]);

        $this->actingAs($usuario)
            ->postJson('/agente/consultar', [
                'imagen' => UploadedFile::fake()->image('vehiculo.jpg'),
            ])
            ->assertOk()
            ->assertJsonPath('estado', 'Registrada sin paso')
            ->assertJsonPath('origen', 'imagen');

        Http::assertSent(fn ($request) => $request->url() === 'http://localhost:8000/inferir'
            && str_contains($request->body(), 'name="imagen"'));
    }

    private function crearUsuario(): Usuario
    {
        return Usuario::create([
            'email_google' => 'operador@example.com',
            'nombre' => 'Operador',
            'rol' => 'operador',
            'activo' => true,
        ]);
    }

    private function crearRegistro(
        string $placa,
        Usuario $usuario,
        ?Tarifa $tarifa = null
    ): Registro {
        return Registro::create([
            'usuario_id' => $usuario->id,
            'tarifa_id' => $tarifa?->id,
            'placa_detectada' => $placa,
            'clase_vehiculo' => 'Liviano',
            'confianza' => 0.95,
            'imagen_url' => 'capturas/test.jpg',
            'estado' => 'procesado',
        ]);
    }
}
