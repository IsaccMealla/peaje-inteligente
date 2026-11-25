<header class="mb-8 rounded-2xl border border-slate-700 bg-slate-800/90 p-5 shadow-xl">
    <div class="flex flex-wrap items-center justify-between gap-5">
        <div>
            <a href="{{ route('dashboard') }}" class="text-xl font-bold tracking-tight text-white">Peaje Inteligente</a>
            <p class="mt-1 text-sm text-slate-400">Control y monitoreo de caseta</p>
        </div>
        <nav class="flex flex-wrap gap-2 text-sm" aria-label="Navegación principal">
            <a href="{{ route('dashboard') }}" class="rounded-lg px-3 py-2 {{ request()->routeIs('dashboard') ? 'bg-blue-600 text-white' : 'text-slate-300 hover:bg-slate-700' }}">Panel en Tiempo Real</a>
            <a href="{{ route('vehiculos.index') }}" class="rounded-lg px-3 py-2 {{ request()->routeIs('vehiculos.*') ? 'bg-blue-600 text-white' : 'text-slate-300 hover:bg-slate-700' }}">Vehículos</a>
            <a href="{{ route('pasos.index') }}" class="rounded-lg px-3 py-2 {{ request()->routeIs('pasos.*') ? 'bg-blue-600 text-white' : 'text-slate-300 hover:bg-slate-700' }}">Pasos</a>
            <a href="{{ route('alertas.index') }}" class="rounded-lg px-3 py-2 {{ request()->routeIs('alertas.*') ? 'bg-blue-600 text-white' : 'text-slate-300 hover:bg-slate-700' }}">Alertas</a>
            <a href="{{ route('modelos.index') }}" class="rounded-lg px-3 py-2 {{ request()->routeIs('modelos.*') ? 'bg-blue-600 text-white' : 'text-slate-300 hover:bg-slate-700' }}">Modelos M1–M4</a>
        </nav>
        <div class="flex items-center gap-3">
            <span class="text-sm text-slate-400">{{ auth()->user()->nombre }}</span>
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button class="rounded-lg border border-slate-600 px-3 py-2 text-sm text-slate-200 hover:bg-slate-700">Salir</button>
            </form>
        </div>
    </div>
</header>
