<!DOCTYPE html>
<html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Alertas - Peaje Inteligente</title><script src="https://cdn.tailwindcss.com"></script></head>
<body class="min-h-screen bg-slate-950 p-4 text-slate-100 md:p-8"><main class="mx-auto max-w-6xl">
    @include('layouts.navigation')
    <div class="mb-6"><h1 class="text-3xl font-bold">Alertas</h1><p class="mt-1 text-slate-400">Vehículos no registrados, saldo insuficiente y revisiones manuales.</p></div>
    <div class="space-y-3">@forelse($alertas as $alerta)
        <article class="flex flex-wrap items-center justify-between gap-4 rounded-xl border {{ $alerta->leida ? 'border-slate-800 bg-slate-900/50' : 'border-amber-500/30 bg-slate-900' }} p-5">
            <div><div class="flex items-center gap-2"><span class="rounded-full bg-amber-500/10 px-2 py-1 text-xs text-amber-300">{{ str_replace('_', ' ', $alerta->tipo) }}</span><span class="text-xs text-slate-500">{{ $alerta->created_at->format('d/m/Y H:i:s') }}</span></div><p class="mt-2 text-slate-200">{{ $alerta->mensaje }}</p><p class="mt-1 text-xs text-slate-500">Paso #{{ $alerta->registro_id ?? '—' }} · {{ $alerta->registro?->placa_detectada ?? 'placa no legible' }}</p></div>
            @unless($alerta->leida)<form action="{{ route('alertas.leida', $alerta) }}" method="POST">@csrf @method('PATCH')<button class="rounded-lg bg-blue-700 px-3 py-2 text-sm">Marcar atendida</button></form>@else<span class="text-xs text-emerald-400">Atendida</span>@endunless
        </article>
    @empty<div class="rounded-xl border border-slate-800 bg-slate-900 p-8 text-center text-slate-500">No hay alertas.</div>@endforelse</div>
    <div class="mt-5">{{ $alertas->links() }}</div>
</main></body></html>
