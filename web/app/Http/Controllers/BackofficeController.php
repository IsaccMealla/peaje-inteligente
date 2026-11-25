<?php

namespace App\Http\Controllers;

use App\Models\Alerta;
use App\Models\Registro;
use App\Models\VehiculoRegistrado;
use App\Services\InferenceHealthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BackofficeController extends Controller
{
    public function vehiculos(): View
    {
        return view('vehiculos', [
            'vehiculos' => VehiculoRegistrado::orderBy('placa')->paginate(30),
        ]);
    }

    public function guardarVehiculo(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'placa' => ['required', 'string', 'max:15', 'unique:vehiculo_registrados,placa'],
            'propietario' => ['nullable', 'string', 'max:255'],
            'categoria' => ['required', 'string', 'max:100'],
            'saldo' => ['required', 'numeric', 'min:0'],
            'activo' => ['sometimes', 'boolean'],
        ]);
        $datos['activo'] = $request->boolean('activo');
        VehiculoRegistrado::create($datos);

        return back()->with('estado', 'Vehículo registrado.');
    }

    public function actualizarVehiculo(Request $request, VehiculoRegistrado $vehiculo): RedirectResponse
    {
        $datos = $request->validate([
            'placa' => ['required', 'string', 'max:15', 'unique:vehiculo_registrados,placa,'.$vehiculo->id],
            'propietario' => ['nullable', 'string', 'max:255'],
            'categoria' => ['required', 'string', 'max:100'],
            'saldo' => ['required', 'numeric', 'min:0'],
            'activo' => ['sometimes', 'boolean'],
        ]);
        $datos['activo'] = $request->boolean('activo');
        $vehiculo->update($datos);

        return back()->with('estado', 'Vehículo actualizado.');
    }

    public function eliminarVehiculo(VehiculoRegistrado $vehiculo): RedirectResponse
    {
        $vehiculo->delete();

        return back()->with('estado', 'Vehículo eliminado.');
    }

    public function pasos(): View
    {
        return view('pasos', [
            'pasos' => Registro::with('tarifa')->latest()->paginate(40),
        ]);
    }

    public function alertas(): View
    {
        return view('alertas', [
            'alertas' => Alerta::with('registro')->latest()->paginate(40),
        ]);
    }

    public function modelos(InferenceHealthService $health): View
    {
        return view('modelos', ['modelos' => $health->status()]);
    }

    public function marcarAlertaLeida(Alerta $alerta): RedirectResponse
    {
        $alerta->update(['leida' => true]);

        return back()->with('estado', 'Alerta marcada como atendida.');
    }
}
