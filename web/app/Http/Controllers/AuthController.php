<?php

namespace App\Http\Controllers;

use App\Models\Registro;
use App\Models\Usuario;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    public function redirect()
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback()
    {
        $googleUser = Socialite::driver('google')->stateless()->user();

        $usuario = Usuario::where('email_google', $googleUser->getEmail())
            ->where('activo', true)
            ->first();

        if (!$usuario) {
            abort(403, 'Acceso denegado: tu correo no está autorizado en la lista blanca.');
        }

        Auth::login($usuario);

        return redirect()->route('dashboard');
    }

    public function dashboard()
    {
        $registros = Registro::with('tarifa')->latest()->take(20)->get();
        return view('dashboard', compact('registros'));
    }

    public function logout()
    {
        Auth::logout();
        return redirect('/');
    }
}