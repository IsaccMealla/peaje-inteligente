<?php

namespace App\Http\Controllers;

use App\Models\Alerta;
use App\Models\Caseta;
use App\Models\Registro;
use App\Models\Tarifa;
use App\Models\VehiculoRegistrado;
use App\Services\InferenceHealthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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

    public function pasos(Request $request): View
    {
        $filtros = $request->validate([
            'placa' => ['nullable', 'string', 'max:15'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
            'estado' => ['nullable', Rule::in(['procesado', 'revision', 'rechazado'])],
        ]);
        $placa = isset($filtros['placa'])
            ? preg_replace('/[^A-Z0-9]/', '', strtoupper($filtros['placa']))
            : null;
        $query = Registro::with(['tarifa', 'caseta'])->latest();

        if ($placa) {
            $query->where('placa_detectada', 'like', '%'.$placa.'%');
        }
        if (! empty($filtros['desde'])) {
            $query->whereDate('created_at', '>=', $filtros['desde']);
        }
        if (! empty($filtros['hasta'])) {
            $query->whereDate('created_at', '<=', $filtros['hasta']);
        }
        if (! empty($filtros['estado'])) {
            $query->where('estado', $filtros['estado']);
        }

        return view('pasos', [
            'pasos' => $query->paginate(30)->withQueryString(),
        ]);
    }

    public function alertas(Request $request): View
    {
        $request->validate(['estado' => ['nullable', Rule::in(['pendientes', 'atendidas'])]]);
        $query = Alerta::with('registro')->latest();
        if ($request->input('estado') === 'pendientes') {
            $query->where('leida', false);
        } elseif ($request->input('estado') === 'atendidas') {
            $query->where('leida', true);
        }

        return view('alertas', [
            'alertas' => $query->paginate(30)->withQueryString(),
        ]);
    }

    public function tarifasCasetas(): View
    {
        return view('tarifas-casetas', [
            'tarifas' => Tarifa::whereIn('categoria_vehiculo', ['moto', 'auto', 'minibus', 'bus', 'camion'])
                ->orderBy('categoria_vehiculo')
                ->orderBy('ejes')
                ->get(),
            'casetas' => Caseta::orderBy('id')->get(),
        ]);
    }

    public function guardarTarifa(Request $request): RedirectResponse
    {
        abort_unless($request->user()->rol === 'admin', 403);
        $datos = $this->validarTarifa($request);
        Tarifa::create($datos);

        return back()->with('estado', 'Tarifa creada correctamente.');
    }

    public function actualizarTarifa(Request $request, Tarifa $tarifa): RedirectResponse
    {
        abort_unless($request->user()->rol === 'admin', 403);
        $tarifa->update($this->validarTarifa($request, $tarifa));

        return back()->with('estado', 'Tarifa actualizada correctamente.');
    }

    public function eliminarTarifa(Request $request, Tarifa $tarifa): RedirectResponse
    {
        abort_unless($request->user()->rol === 'admin', 403);
        $tarifa->delete();

        return back()->with('estado', 'Tarifa eliminada.');
    }

    public function guardarCaseta(Request $request): RedirectResponse
    {
        abort_unless($request->user()->rol === 'admin', 403);
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:255', 'unique:casetas,nombre'],
            'ubicacion' => ['nullable', 'string', 'max:255'],
        ]);
        $datos['activa'] = true;
        Caseta::create($datos);

        return back()->with('estado', 'Caseta creada correctamente.');
    }

    public function actualizarCaseta(Request $request, Caseta $caseta): RedirectResponse
    {
        abort_unless($request->user()->rol === 'admin', 403);
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:255', Rule::unique('casetas')->ignore($caseta->id)],
            'ubicacion' => ['nullable', 'string', 'max:255'],
            'activa' => ['sometimes', 'boolean'],
        ]);
        $datos['activa'] = $request->boolean('activa');
        $caseta->update($datos);

        return back()->with('estado', 'Caseta actualizada correctamente.');
    }

    public function eliminarCaseta(Request $request, Caseta $caseta): RedirectResponse
    {
        abort_unless($request->user()->rol === 'admin', 403);
        $caseta->delete();

        return back()->with('estado', 'Caseta eliminada.');
    }

    public function modelos(InferenceHealthService $health): View
    {
        return view('modelos', ['modelos' => $health->status()]);
    }

    public function marcarAlertaLeida(Request $request, Alerta $alerta): RedirectResponse|JsonResponse
    {
        $alerta->update(['leida' => true]);

        if ($request->expectsJson()) {
            return response()->json(['id' => $alerta->id, 'leida' => true]);
        }

        return back()->with('estado', 'Alerta marcada como atendida.');
    }

    private function validarTarifa(Request $request, ?Tarifa $tarifa = null): array
    {
        $datos = $request->validate([
            'categoria_vehiculo' => ['required', Rule::in(['moto', 'auto', 'minibus', 'bus', 'camion'])],
            'ejes' => ['required', 'integer', 'between:1,12'],
            'monto' => ['required', 'numeric', 'min:0', 'max:999999'],
            'descripcion' => ['nullable', 'string', 'max:255'],
        ]);

        $duplicada = Tarifa::where('categoria_vehiculo', $datos['categoria_vehiculo'])
            ->where('ejes', $datos['ejes'])
            ->when($tarifa, fn ($query) => $query->where('id', '!=', $tarifa->id))
            ->exists();
        if ($duplicada) {
            throw ValidationException::withMessages([
                'categoria_vehiculo' => 'Ya existe una tarifa para esta categoría y cantidad de ejes.',
            ]);
        }

        return $datos;
    }
}
