<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Historial de Pasos - Peaje Inteligente</title><script src="https://cdn.tailwindcss.com"></script></head>
<body class="min-h-screen bg-slate-950 p-4 text-slate-100 md:p-8">
<main class="mx-auto max-w-7xl">
    @include('layouts.navigation')
    <div class="mb-6"><p class="text-sm font-semibold uppercase tracking-wide text-blue-600">Auditoría</p><h1 class="mt-2 text-3xl font-bold">Historial de Pasos</h1><p class="mt-1 text-slate-500">Busca por placa, fecha y estado; abre la evidencia fotográfica para ampliarla.</p></div>
    <form method="GET" action="{{ route('pasos.index') }}" class="mb-5 grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 md:grid-cols-5">
        <label class="text-xs font-medium text-slate-600">Placa<input name="placa" value="{{ request('placa') }}" placeholder="Ej. 4021XYZ" class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm"></label>
        <label class="text-xs font-medium text-slate-600">Desde<input name="desde" type="date" value="{{ request('desde') }}" class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm"></label>
        <label class="text-xs font-medium text-slate-600">Hasta<input name="hasta" type="date" value="{{ request('hasta') }}" class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm"></label>
        <label class="text-xs font-medium text-slate-600">Estado
            <select name="estado" class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                <option value="">Todos</option><option value="procesado" @selected(request('estado') === 'procesado')>Procesado</option><option value="revision" @selected(request('estado') === 'revision')>Revisión</option><option value="rechazado" @selected(request('estado') === 'rechazado')>Rechazado</option>
            </select>
        </label>
        <div class="flex items-end gap-2"><button class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white">Filtrar</button><a href="{{ route('pasos.index') }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm">Limpiar</a></div>
    </form>
    <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
        <table class="w-full min-w-[1180px] text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="p-4">Evidencia</th><th class="p-4">Placa Normalizada / Leída</th><th class="p-4">Confianza</th><th class="p-4">Categoría</th><th class="p-4">Ejes / Pasajeros</th><th class="p-4">Caseta</th><th class="p-4">Monto</th><th class="p-4">Estado</th><th class="p-4">Fecha / Hora</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
            @forelse($pasos as $paso)
                @php($placaLeida = $paso->placa_detectada ?: 'No legible')
                @php($placaNormalizada = $paso->placa_detectada ? preg_replace('/[^A-Z0-9]/', '', strtoupper($paso->placa_detectada)) : '—')
                <tr class="hover:bg-slate-50">
                    <td class="p-3"><button type="button" class="abrir-evidencia" data-src="{{ route('registros.captura', $paso) }}" aria-label="Ampliar evidencia"><img src="{{ route('registros.captura', $paso) }}" alt="Evidencia del paso {{ $paso->id }}" loading="lazy" class="h-20 w-32 cursor-zoom-in rounded-lg border border-slate-200 object-cover"></button></td>
                    <td class="p-4"><p class="font-mono font-bold text-slate-900">{{ $placaNormalizada }}</p><p class="mt-1 text-xs text-slate-500">Leída: {{ $placaLeida }}</p></td>
                    <td class="p-4">{{ number_format((float) $paso->confianza * 100, 1) }}%</td>
                    <td class="p-4">{{ $paso->clase_vehiculo ?? '—' }}</td>
                    <td class="p-4">{{ $paso->ejes ?? 0 }} ejes · {{ $paso->pasajeros ?? 0 }} pasajeros</td>
                    <td class="p-4">{{ $paso->caseta?->nombre ?? '—' }}</td>
                    <td class="p-4 font-semibold">Bs. {{ number_format((float) ($paso->tarifa?->monto ?? 0), 2) }}</td>
                    <td class="p-4"><span class="rounded-full px-2.5 py-1 text-xs {{ $paso->estado === 'procesado' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">{{ ucfirst($paso->estado) }}</span></td>
                    <td class="p-4 whitespace-nowrap text-slate-500">{{ $paso->created_at->timezone('America/La_Paz')->format('d/m/Y H:i:s') }}</td>
                </tr>
            @empty<tr><td colspan="9" class="p-10 text-center text-slate-500">No hay pasos que coincidan con los filtros.</td></tr>@endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-5">{{ $pasos->links() }}</div>
</main>
<div id="modal-evidencia" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/80 p-4" role="dialog" aria-modal="true"><button id="cerrar-evidencia" class="absolute right-5 top-3 text-3xl text-white" aria-label="Cerrar">×</button><img id="imagen-evidencia" src="" alt="Evidencia ampliada" class="max-h-[90vh] max-w-[95vw] rounded-xl object-contain"></div>
<script>
    const modal=document.getElementById('modal-evidencia'), imagen=document.getElementById('imagen-evidencia');
    document.querySelectorAll('.abrir-evidencia').forEach((boton)=>boton.addEventListener('click',()=>{imagen.src=boton.dataset.src;modal.classList.remove('hidden');modal.classList.add('flex');}));
    function cerrar(){modal.classList.add('hidden');modal.classList.remove('flex');imagen.src='';}
    document.getElementById('cerrar-evidencia').addEventListener('click',cerrar);
    modal.addEventListener('click',(event)=>{if(event.target===modal)cerrar();});
</script>
</body>
</html>
