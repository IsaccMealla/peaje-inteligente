<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UsuarioOAuthController extends Controller
{
    public function index(): View
    {
        $usuarios = Usuario::query()->orderBy('nombre')->get();

        return view('usuarios.index', [
            'usuarios' => $usuarios,
            'totalCuentas' => $usuarios->count(),
            'accesosAutorizados' => $usuarios->where('activo', true)->count(),
            'totalAdmins' => $usuarios->where('rol', 'admin')->count(),
            'totalOperadores' => $usuarios->where('rol', 'operador')->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'email_google' => ['required', 'email', 'max:255', 'unique:usuarios,email_google'],
            'rol' => ['required', 'in:admin,operador'],
        ]);
        $datos['activo'] = true;

        Usuario::create($datos);

        return back()->with('estado', 'Cuenta Google autorizada correctamente.');
    }

    public function update(Request $request, Usuario $usuario): RedirectResponse
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'email_google' => ['required', 'email', 'max:255', 'unique:usuarios,email_google,'.$usuario->id],
            'rol' => ['required', 'in:admin,operador'],
        ]);

        if ($usuario->id === auth()->id() && $datos['rol'] !== 'admin') {
            return back()->withErrors([
                'usuario' => 'No puedes cambiar tu propio rol de administrador.',
            ]);
        }

        $usuario->update($datos);

        return back()->with('estado', 'Cuenta actualizada correctamente.');
    }

    public function toggle(Usuario $usuario): RedirectResponse
    {
        if ($usuario->id === auth()->id()) {
            return back()->withErrors([
                'usuario' => 'No puedes revocar tu propia cuenta.',
            ]);
        }

        $usuario->update(['activo' => ! $usuario->activo]);

        return back()->with(
            'estado',
            $usuario->activo ? 'Acceso autorizado nuevamente.' : 'Acceso revocado correctamente.'
        );
    }

    public function destroy(Usuario $usuario): RedirectResponse
    {
        if ($usuario->id === auth()->id()) {
            return back()->withErrors([
                'usuario' => 'No puedes eliminar tu propia cuenta.',
            ]);
        }

        $usuario->delete();

        return back()->with('estado', 'Cuenta eliminada de la lista blanca.');
    }
}
