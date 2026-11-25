<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Peaje Inteligente</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen p-6">
    <div class="max-w-6xl mx-auto">
        <header class="flex justify-between items-center mb-8 border-b border-slate-700 pb-4">
            <div>
                <h1 class="text-2xl font-bold text-white">Monitor de Peaje en Tiempo Real</h1>
                <p class="text-sm text-slate-400">Operador: {{ auth()->user()->nombre }} ({{ auth()->user()->rol }})</p>
            </div>
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button class="bg-red-600 hover:bg-red-500 px-4 py-2 rounded-lg text-sm font-medium">Cerrar sesión</button>
            </form>
        </header>

        <div class="bg-slate-800 rounded-xl border border-slate-700 overflow-hidden">
            <table class="w-full text-left border-collapse">
                <thead class="bg-slate-700/50 text-slate-300 text-xs uppercase">
                    <tr>
                        <th class="p-4">Hora</th>
                        <th class="p-4">Captura</th>
                        <th class="p-4">Placa</th>
                        <th class="p-4">Categoría</th>
                        <th class="p-4">Tarifa</th>
                        <th class="p-4">Confianza</th>
                        <th class="p-4">Estado</th>
                    </tr>
                </thead>
                <tbody id="tabla-registros" class="divide-y divide-slate-700 text-sm">
                    @foreach($registros as $reg)
                    <tr>
                        <td class="p-4 text-slate-400">{{ $reg->created_at->format('H:i:s') }}</td>
                        <td class="p-4"><img src="{{ asset('storage/' . $reg->imagen_url) }}" class="w-16 h-12 object-cover rounded"></td>
                        <td class="p-4 font-mono font-bold text-amber-400">{{ $reg->placa_detectada ?? 'No legible' }}</td>
                        <td class="p-4 capitalize">{{ $reg->clase_vehiculo }}</td>
                        <td class="p-4 font-semibold">Bs. {{ number_format($reg->tarifa?->monto ?? 0, 2) }}</td>
                        <td class="p-4">{{ round($reg->confianza * 100, 1) }}%</td>
                        <td class="p-4">
                            <span class="px-2 py-1 rounded text-xs {{ $reg->estado === 'procesado' ? 'bg-emerald-500/20 text-emerald-300' : 'bg-amber-500/20 text-amber-300' }}">
                                {{ $reg->estado }}
                            </span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <script>
        const pusher = new Pusher("{{ config('broadcasting.connections.pusher.key') }}", {
            cluster: "{{ config('broadcasting.connections.pusher.options.cluster') }}"
        });

        const channel = pusher.subscribe('peaje-canal');
        channel.bind('nuevo-registro', function(data) {
            const tbody = document.getElementById('tabla-registros');
            const badgeClass = data.estado === 'procesado' ? 'bg-emerald-500/20 text-emerald-300' : 'bg-amber-500/20 text-amber-300';
            const fila = `
                <tr class="bg-blue-900/20 transition-colors">
                    <td class="p-4 text-slate-400">${data.hora}</td>
                    <td class="p-4"><img src="${data.imagen_url}" class="w-16 h-12 object-cover rounded"></td>
                    <td class="p-4 font-mono font-bold text-amber-400">${data.placa}</td>
                    <td class="p-4 capitalize">${data.categoria}</td>
                    <td class="p-4 font-semibold">Bs. ${Number(data.tarifa).toFixed(2)}</td>
                    <td class="p-4">${data.confianza}%</td>
                    <td class="p-4"><span class="px-2 py-1 rounded text-xs ${badgeClass}">${data.estado}</span></td>
                </tr>
            `;
            tbody.insertAdjacentHTML('afterbegin', fila);
        });
    </script>
</body>
</html>