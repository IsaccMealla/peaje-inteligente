<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tarifas y Casetas - Peaje Inteligente</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-950 p-4 text-slate-100 md:p-8">
<main class="mx-auto max-w-7xl">
    @include('layouts.navigation')
    <div class="mb-6">
        <p class="text-sm font-semibold uppercase tracking-[.18em] text-blue-600">Configuración operativa</p>
        <h1 class="mt-2 text-3xl font-bold">Tarifas y Casetas</h1>
        <p class="mt-1 text-slate-500">Tarifas por categoría y ejes; administra las casetas activas del sistema.</p>
    </div>
    @if(session('estado'))<div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-700">{{ session('estado') }}</div>@endif
    @if($errors->any())<div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 p-4 text-rose-700">{{ $errors->first() }}</div>@endif

    <div class="grid items-start gap-6 xl:grid-cols-2">
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 p-5">
                <div><h2 class="text-xl font-bold">Tarifas Vigentes</h2><p class="mt-1 text-sm text-slate-500">El cobro se selecciona por categoría y número de ejes.</p></div>
                @if(auth()->user()->rol === 'admin')
                    <button type="button" onclick="document.getElementById('modal-tarifa').classList.remove('hidden')" class="rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">+ Nueva Tarifa</button>
                @endif
            </div>
            <div class="divide-y divide-slate-100">
                @forelse($tarifas as $tarifa)
                    <div class="p-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <span class="font-semibold capitalize">{{ $tarifa->categoria_vehiculo }}</span>
                                <span class="ml-2 rounded-full bg-slate-100 px-2.5 py-1 text-xs text-slate-600">{{ $tarifa->ejes }} {{ $tarifa->ejes === 1 ? 'eje' : 'ejes' }}</span>
                                <p class="mt-1 text-sm text-slate-500">{{ $tarifa->descripcion ?: 'Tarifa del peaje' }}</p>
                            </div>
                            <span class="text-xl font-bold text-slate-900">Bs. {{ number_format((float) $tarifa->monto, 2) }}</span>
                        </div>
                        @if(auth()->user()->rol === 'admin')
                            <details class="mt-3">
                                <summary class="cursor-pointer text-xs font-semibold text-blue-700">Editar tarifa</summary>
                                <form action="{{ route('tarifas.update', $tarifa) }}" method="POST" class="mt-3 grid gap-2 rounded-xl bg-slate-50 p-3 sm:grid-cols-2">
                                    @csrf @method('PUT')
                                    <label class="text-xs text-slate-600">Categoría
                                        <select name="categoria_vehiculo" required class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                                            @foreach(['moto', 'auto', 'minibus', 'bus', 'camion'] as $categoria)<option value="{{ $categoria }}" @selected($tarifa->categoria_vehiculo === $categoria)>{{ ucfirst($categoria) }}</option>@endforeach
                                        </select>
                                    </label>
                                    <label class="text-xs text-slate-600">Ejes
                                        <input name="ejes" type="number" min="1" max="12" value="{{ $tarifa->ejes }}" required class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                                    </label>
                                    <label class="text-xs text-slate-600">Monto (Bs.)
                                        <input name="monto" type="number" min="0" step="0.01" value="{{ $tarifa->monto }}" required class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                                    </label>
                                    <label class="text-xs text-slate-600">Descripción
                                        <input name="descripcion" value="{{ $tarifa->descripcion }}" class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                                    </label>
                                    <div class="flex gap-2 sm:col-span-2">
                                        <button class="rounded-lg bg-blue-600 px-3 py-2 text-xs font-semibold text-white">Guardar cambios</button>
                                        <button form="eliminar-tarifa-{{ $tarifa->id }}" class="rounded-lg border border-rose-200 px-3 py-2 text-xs font-semibold text-rose-700" onclick="return confirm('¿Eliminar esta tarifa?')">Eliminar</button>
                                    </div>
                                </form>
                                <form id="eliminar-tarifa-{{ $tarifa->id }}" action="{{ route('tarifas.destroy', $tarifa) }}" method="POST">@csrf @method('DELETE')</form>
                            </details>
                        @endif
                    </div>
                @empty
                    <p class="p-8 text-center text-slate-500">Todavía no hay tarifas configuradas.</p>
                @endforelse
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 p-5">
                <div><h2 class="text-xl font-bold">Casetas de Peaje</h2><p class="mt-1 text-sm text-slate-500">Puntos de control y su ubicación.</p></div>
                @if(auth()->user()->rol === 'admin')
                    <button type="button" onclick="document.getElementById('modal-caseta').classList.remove('hidden')" class="rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">+ Nueva Caseta</button>
                @endif
            </div>
            <div class="divide-y divide-slate-100">
                @forelse($casetas as $caseta)
                    <div class="p-4">
                        <form id="caseta-{{ $caseta->id }}" action="{{ route('casetas.update', $caseta) }}" method="POST" class="grid gap-2 sm:grid-cols-[auto_1fr_1fr_auto] sm:items-end">
                            @csrf @method('PUT')
                            <span class="rounded-lg bg-slate-100 px-3 py-2 text-xs font-semibold text-slate-600">ID {{ $caseta->id }}</span>
                            <label class="text-xs text-slate-600">Nombre
                                <input name="nombre" value="{{ $caseta->nombre }}" required class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm" @disabled(auth()->user()->rol !== 'admin')>
                            </label>
                            <label class="text-xs text-slate-600">Ubicación
                                <input name="ubicacion" value="{{ $caseta->ubicacion }}" class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm" @disabled(auth()->user()->rol !== 'admin')>
                            </label>
                            @if(auth()->user()->rol === 'admin')
                                <label class="flex items-center gap-2 pb-2 text-xs text-slate-600">
                                    <input type="checkbox" name="activa" value="1" @checked($caseta->activa) class="accent-blue-600"> Activa
                                </label>
                            @else
                                <span class="pb-2 text-xs {{ $caseta->activa ? 'text-emerald-700' : 'text-slate-500' }}">{{ $caseta->activa ? 'Activa' : 'Inactiva' }}</span>
                            @endif
                        </form>
                        @if(auth()->user()->rol === 'admin')
                            <div class="mt-2 flex gap-2 pl-0 sm:pl-[4.5rem]">
                                <button form="caseta-{{ $caseta->id }}" class="rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white">Guardar</button>
                                <form action="{{ route('casetas.destroy', $caseta) }}" method="POST" onsubmit="return confirm('¿Eliminar esta caseta?')">@csrf @method('DELETE')<button class="rounded-lg border border-rose-200 px-3 py-1.5 text-xs font-semibold text-rose-700">Eliminar</button></form>
                            </div>
                        @endif
                    </div>
                @empty
                    <p class="p-8 text-center text-slate-500">No hay casetas registradas.</p>
                @endforelse
            </div>
        </section>
    </div>
</main>

@if(auth()->user()->rol === 'admin')
<div id="modal-tarifa" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/40 p-4" onclick="if(event.target===this)this.classList.add('hidden')">
    <form action="{{ route('tarifas.store') }}" method="POST" class="w-full max-w-lg space-y-4 rounded-2xl bg-white p-6 shadow-2xl" onclick="event.stopPropagation()">
        @csrf
        <h2 class="text-xl font-bold">Nueva tarifa</h2>
        <div class="grid gap-3 sm:grid-cols-2">
            <label class="text-sm text-slate-600">Categoría
                <select name="categoria_vehiculo" required class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2">
                    @foreach(['moto', 'auto', 'minibus', 'bus', 'camion'] as $categoria)<option value="{{ $categoria }}">{{ ucfirst($categoria) }}</option>@endforeach
                </select>
            </label>
            <label class="text-sm text-slate-600">Ejes <input name="ejes" type="number" min="1" max="12" value="2" required class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2"></label>
            <label class="text-sm text-slate-600">Monto (Bs.) <input name="monto" type="number" min="0" step="0.01" required class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2"></label>
            <label class="text-sm text-slate-600">Descripción <input name="descripcion" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2"></label>
        </div>
        <div class="flex justify-end gap-2"><button type="button" onclick="document.getElementById('modal-tarifa').classList.add('hidden')" class="rounded-lg border border-slate-200 px-4 py-2">Cancelar</button><button class="rounded-lg bg-blue-600 px-4 py-2 font-semibold text-white">Crear tarifa</button></div>
    </form>
</div>
<div id="modal-caseta" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/40 p-4" onclick="if(event.target===this)this.classList.add('hidden')">
    <form action="{{ route('casetas.store') }}" method="POST" class="w-full max-w-lg space-y-4 rounded-2xl bg-white p-6 shadow-2xl" onclick="event.stopPropagation()">
        @csrf
        <h2 class="text-xl font-bold">Nueva caseta</h2>
        <label class="block text-sm text-slate-600">Nombre <input name="nombre" required class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2"></label>
        <label class="block text-sm text-slate-600">Ubicación <input name="ubicacion" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2"></label>
        <div class="flex justify-end gap-2"><button type="button" onclick="document.getElementById('modal-caseta').classList.add('hidden')" class="rounded-lg border border-slate-200 px-4 py-2">Cancelar</button><button class="rounded-lg bg-blue-600 px-4 py-2 font-semibold text-white">Crear caseta</button></div>
    </form>
</div>
@endif
</body>
</html>
