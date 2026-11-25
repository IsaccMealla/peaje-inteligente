<!DOCTYPE html>
<html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Historial de pasos - Peaje Inteligente</title><script src="https://cdn.tailwindcss.com"></script></head>
<body class="min-h-screen bg-slate-950 p-4 text-slate-100 md:p-8"><main class="mx-auto max-w-7xl">
    @include('layouts.navigation')
    <div class="mb-6"><h1 class="text-3xl font-bold">Historial de pasos</h1><p class="mt-1 text-slate-400">Registros procesados por la caseta.</p></div>
    <div class="overflow-x-auto rounded-2xl border border-slate-800 bg-slate-900"><table class="w-full min-w-[1050px] text-left text-sm">
        <thead class="bg-slate-800 text-xs uppercase text-slate-400"><tr><th class="p-4">Fecha</th><th class="p-4">Captura</th><th class="p-4">Placa</th><th class="p-4">Categoría</th><th class="p-4">Ejes</th><th class="p-4">Pasajeros</th><th class="p-4">Tarifa</th><th class="p-4">Confianza</th><th class="p-4">Estado</th></tr></thead>
        <tbody class="divide-y divide-slate-800">@forelse($pasos as $paso)
            <tr><td class="p-4 text-slate-400">{{ $paso->created_at->format('d/m/Y H:i:s') }}</td><td class="p-4"><a href="{{ route('registros.captura', $paso) }}" target="_blank"><img src="{{ route('registros.captura', $paso) }}" alt="Captura" class="h-20 w-[120px] rounded-lg object-cover"></a></td><td class="p-4 font-mono font-bold text-amber-300">{{ $paso->placa_detectada ?? 'No legible' }}</td><td class="p-4">{{ $paso->clase_vehiculo }}</td><td class="p-4">{{ $paso->ejes ?? 0 }}</td><td class="p-4">{{ $paso->pasajeros ?? 0 }}</td><td class="p-4">Bs. {{ number_format($paso->tarifa?->monto ?? 0, 2) }}</td><td class="p-4">{{ round($paso->confianza * 100, 1) }}%</td><td class="p-4">{{ $paso->estado }}</td></tr>
        @empty<tr><td colspan="9" class="p-8 text-center text-slate-500">No hay pasos registrados.</td></tr>@endforelse</tbody>
    </table></div><div class="mt-5">{{ $pasos->links() }}</div>
</main></body></html>
