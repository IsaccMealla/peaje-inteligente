<!DOCTYPE html>
<html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Estado de modelos - Peaje Inteligente</title><script src="https://cdn.tailwindcss.com"></script></head>
<body class="min-h-screen bg-slate-950 p-4 text-slate-100 md:p-8"><main class="mx-auto max-w-6xl">
    @include('layouts.navigation')
    <div class="mb-6"><h1 class="text-3xl font-bold">Estado de modelos IA</h1><p class="mt-1 text-slate-400">Disponibilidad del servicio de inferencia.</p></div>
    <div class="grid gap-4 sm:grid-cols-2">
        @foreach($modelos as $id => $modelo)
            <article class="rounded-2xl border border-slate-800 bg-slate-900 p-6">
                <div class="flex items-center justify-between gap-4"><h2 class="text-lg font-semibold">{{ $modelo['nombre'] }}</h2><span class="rounded-full px-3 py-1 text-sm {{ $modelo['estado'] === 'Operativo' ? 'bg-emerald-500/10 text-emerald-300' : 'bg-rose-500/10 text-rose-300' }}">{{ $modelo['estado'] }}</span></div>
                <p class="mt-4 text-sm text-slate-400">Latencia: {{ $modelo['latencia_ms'] === null ? '—' : $modelo['latencia_ms'].' ms' }}</p>
                @if(isset($modelo['mensaje']))<p class="mt-2 text-xs text-rose-300">{{ $modelo['mensaje'] }}</p>@endif
            </article>
        @endforeach
    </div>
</main></body></html>
