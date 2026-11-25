<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Alertas - Peaje Inteligente</title><script src="https://cdn.tailwindcss.com"></script></head>
<body class="min-h-screen bg-slate-950 p-4 text-slate-100 md:p-8">
<main class="mx-auto max-w-6xl">
    @include('layouts.navigation')
    <div class="mb-6"><p class="text-sm font-semibold uppercase tracking-wide text-blue-600">Control de incidencias</p><h1 class="mt-2 text-3xl font-bold">Alertas de Caseta</h1><p class="mt-1 text-slate-500">Revisa placas no registradas, saldo insuficiente y capturas que requieren validación.</p></div>
    @if(session('estado'))<div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-emerald-800">{{ session('estado') }}</div>@endif
    <form method="GET" class="mb-5 flex flex-wrap items-end gap-3 rounded-2xl border border-slate-200 bg-white p-4">
        <label class="text-xs font-medium text-slate-600">Estado de atención
            <select name="estado" class="mt-1 block rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                <option value="">Todas las alertas</option>
                <option value="pendientes" @selected(request('estado') === 'pendientes')>Pendientes</option>
                <option value="atendidas" @selected(request('estado') === 'atendidas')>Atendidas</option>
            </select>
        </label>
        <button class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white">Filtrar</button>
        <a href="{{ route('alertas.index') }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm">Limpiar</a>
        <span class="ml-auto text-sm text-slate-500">{{ $alertas->total() }} alertas</span>
    </form>
    <div class="space-y-3">
        @forelse($alertas as $alerta)
            @php($tipo = match($alerta->tipo) {'vehiculo_no_registrado' => 'PLACA_NO_REGISTRADA', 'saldo_insuficiente' => 'SIN_SALDO', default => strtoupper(str_replace('_', ' ', $alerta->tipo))})
            <article class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border {{ $alerta->leida ? 'border-slate-200 bg-white' : 'border-amber-200 bg-white shadow-sm' }} p-5">
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="rounded-full {{ $alerta->leida ? 'bg-slate-100 text-slate-600' : 'bg-amber-50 text-amber-700' }} px-2.5 py-1 text-xs font-semibold">{{ $tipo }}</span>
                        <span class="text-xs text-slate-500">{{ $alerta->created_at->timezone('America/La_Paz')->format('d/m/Y H:i:s') }}</span>
                        <span class="rounded-full {{ $alerta->leida ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }} px-2.5 py-1 text-xs">{{ $alerta->leida ? 'Atendida' : 'Pendiente' }}</span>
                    </div>
                    <p class="mt-2 text-sm text-slate-800">{{ $alerta->mensaje }}</p>
                    <p class="mt-1 text-xs text-slate-500">Paso #{{ $alerta->registro_id ?? '—' }} · {{ $alerta->registro?->placa_detectada ?? 'Placa no legible' }}</p>
                </div>
                @unless($alerta->leida)
                    <form action="{{ route('alertas.leida', $alerta) }}" method="POST">@csrf @method('PATCH')<button class="rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">Marcar Atendida</button></form>
                @endunless
            </article>
        @empty
            <div class="rounded-2xl border border-slate-200 bg-white p-10 text-center text-slate-500">No hay alertas para este filtro.</div>
        @endforelse
    </div>
    <div class="mt-5">{{ $alertas->links() }}</div>
</main>
</body>
</html>
