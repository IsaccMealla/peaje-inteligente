<?php

namespace Tests\Feature;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UsuarioOAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admins_can_open_the_oauth_allowlist(): void
    {
        $operador = $this->crearUsuario('operador', 'operador@example.com');

        $this->get('/usuarios-oauth')->assertRedirect('/');
        $this->actingAs($operador)->get('/usuarios-oauth')->assertForbidden();
    }

    public function test_admin_can_create_update_revoke_reactivate_and_delete_accounts(): void
    {
        $admin = $this->crearUsuario('admin', 'admin@example.com');
        $this->actingAs($admin);

        $this->get('/usuarios-oauth')
            ->assertOk()
            ->assertSee('Usuarios y Lista Blanca (Google OAuth)')
            ->assertSee('Usuarios & OAuth');

        $this->post('/usuarios-oauth', [
            'nombre' => 'Operadora Uno',
            'email_google' => 'operadora@example.com',
            'rol' => 'operador',
        ])->assertRedirect();
        $cuenta = Usuario::where('email_google', 'operadora@example.com')->firstOrFail();
        $this->assertTrue($cuenta->activo);

        $this->put("/usuarios-oauth/{$cuenta->id}", [
            'nombre' => 'Operadora Editada',
            'email_google' => 'operadora.editada@example.com',
            'rol' => 'admin',
        ])->assertRedirect();
        $this->assertDatabaseHas('usuarios', [
            'id' => $cuenta->id,
            'nombre' => 'Operadora Editada',
            'email_google' => 'operadora.editada@example.com',
            'rol' => 'admin',
        ]);

        $this->patch("/usuarios-oauth/{$cuenta->id}/toggle")->assertRedirect();
        $this->assertDatabaseHas('usuarios', ['id' => $cuenta->id, 'activo' => false]);
        $this->patch("/usuarios-oauth/{$cuenta->id}/toggle")->assertRedirect();
        $this->assertDatabaseHas('usuarios', ['id' => $cuenta->id, 'activo' => true]);
        $this->delete("/usuarios-oauth/{$cuenta->id}")->assertRedirect();
        $this->assertDatabaseMissing('usuarios', ['id' => $cuenta->id]);
    }

    public function test_admin_cannot_revoke_delete_or_demote_themselves(): void
    {
        $admin = $this->crearUsuario('admin', 'admin@example.com');
        $this->actingAs($admin);

        $this->patch("/usuarios-oauth/{$admin->id}/toggle")->assertSessionHasErrors('usuario');
        $this->delete("/usuarios-oauth/{$admin->id}")->assertSessionHasErrors('usuario');
        $this->put("/usuarios-oauth/{$admin->id}", [
            'nombre' => 'Administrador',
            'email_google' => 'admin@example.com',
            'rol' => 'operador',
        ])->assertSessionHasErrors('usuario');

        $this->assertDatabaseHas('usuarios', [
            'id' => $admin->id,
            'rol' => 'admin',
            'activo' => true,
        ]);
    }

    public function test_database_seeder_ensures_the_teacher_admin_account(): void
    {
        $this->seed();

        $this->assertDatabaseHas('usuarios', [
            'email_google' => 'doc.alvaro.pinto.ma@unifranz.edu.bo',
            'nombre' => 'Ing. Alvaro Pinto (Docente)',
            'rol' => 'admin',
            'activo' => true,
        ]);
    }

    private function crearUsuario(string $rol, string $correo): Usuario
    {
        return Usuario::create([
            'nombre' => ucfirst($rol),
            'email_google' => $correo,
            'rol' => $rol,
            'activo' => true,
        ]);
    }
}
