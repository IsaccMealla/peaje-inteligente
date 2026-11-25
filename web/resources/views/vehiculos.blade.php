<!DOCTYPE html>
<html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Vehículos - Peaje Inteligente</title><script src="https://cdn.tailwindcss.com"></script></head>
<body class="min-h-screen bg-slate-950 p-4 text-slate-100 md:p-8"><main class="mx-auto max-w-7xl">
    @include('layouts.navigation')
    <div class="mb-6"><h1 class="text-3xl font-bold">Vehículos registrados</h1><p class="mt-1 text-slate-400">Administra placas, categoría, estado y saldo disponible.</p></div>
    @if(session('estado'))<div class="mb-4 rounded-lg bg-emerald-500/10 p-3 text-emerald-300">{{ session('estado') }}</div>@endif
    @if($errors->any())<div class="mb-4 rounded-lg bg-rose-500/10 p-3 text-rose-300">{{ $errors->first() }}</div>@endif
    <form action="{{ route('vehiculos.store') }}" method="POST" class="mb-6 grid gap-3 rounded-2xl border border-slate-800 bg-slate-900 p-5 md:grid-cols-5">
        @csrf
        <input name="placa" required maxlength="15" placeholder="Placa" class="rounded-lg border border-slate-700 bg-slate-950 px-3 py-2">
        <input name="propietario" placeholder="Propietario" class="rounded-lg border border-slate-700 bg-slate-950 px-3 py-2">
        <select name="categoria" required class="rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"><option>Liviano</option><option>Motocicleta</option><option>Minibús</option><option>Bus</option><option>Pesado</option></select>
        <input name="saldo" type="number" step="0.01" min="0" value="0" required placeholder="Saldo Bs." class="rounded-lg border border-slate-700 bg-slate-950 px-3 py-2">
        <button class="rounded-lg bg-blue-600 px-4 py-2 font-semibold hover:bg-blue-500">Registrar vehículo</button>
    </form>
    <div class="overflow-x-auto rounded-2xl border border-slate-800 bg-slate-900">
        <table class="w-full min-w-[900px] text-left text-sm"><thead class="bg-slate-800 text-xs uppercase text-slate-400"><tr><th class="p-4">Placa</th><th class="p-4">Propietario</th><th class="p-4">Categoría</th><th class="p-4">Saldo Bs.</th><th class="p-4">Activo</th><th class="p-4">Acciones</th></tr></thead><tbody class="divide-y divide-slate-800">
        @forelse($vehiculos as $vehiculo)
            <tr>
                <td class="p-3"><form id="vehiculo-{{ $vehiculo->id }}" action="{{ route('vehiculos.update', $vehiculo) }}" method="POST">@csrf @method('PUT')</form><input form="vehiculo-{{ $vehiculo->id }}" name="placa" value="{{ $vehiculo->placa }}" required class="w-28 rounded bg-slate-950 px-2 py-2 font-mono"></td>
                <td class="p-3"><input form="vehiculo-{{ $vehiculo->id }}" name="propietario" value="{{ $vehiculo->propietario }}" class="w-48 rounded bg-slate-950 px-2 py-2"></td>
                <td class="p-3"><input form="vehiculo-{{ $vehiculo->id }}" name="categoria" value="{{ $vehiculo->categoria }}" required class="w-36 rounded bg-slate-950 px-2 py-2"></td>
                <td class="p-3"><input form="vehiculo-{{ $vehiculo->id }}" name="saldo" type="number" min="0" step="0.01" value="{{ $vehiculo->saldo }}" required class="w-28 rounded bg-slate-950 px-2 py-2"></td>
                <td class="p-3"><input form="vehiculo-{{ $vehiculo->id }}" type="checkbox" name="activo" value="1" @checked($vehiculo->activo) class="h-4 w-4 accent-blue-500"></td>
                <td class="p-3"><div class="flex gap-2"><button form="vehiculo-{{ $vehiculo->id }}" class="rounded bg-emerald-700 px-3 py-2">Guardar</button><form action="{{ route('vehiculos.destroy', $vehiculo) }}" method="POST" onsubmit="return confirm('¿Eliminar este vehículo?')">@csrf @method('DELETE')<button class="rounded bg-rose-700 px-3 py-2">Eliminar</button></form></div></td>
            </tr>
        @empty<tr><td colspan="6" class="p-8 text-center text-slate-500">No hay vehículos.</td></tr>@endforelse
        </tbody></table>
    </div>
    <div class="mt-5">{{ $vehiculos->links() }}</div>
</main></body></html>
