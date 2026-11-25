<style>
    body.bg-slate-950 { background: #f8fafc !important; color: #0f172a !important; }
    .bg-slate-950 { background-color: #f8fafc !important; }
    .bg-slate-900 { background-color: #fff !important; }
    .bg-slate-800, .bg-slate-800\/70, .bg-slate-800\/80, .bg-slate-800\/90 { background-color: #f1f5f9 !important; }
    .border-slate-800, .border-slate-700 { border-color: #e2e8f0 !important; }
    .text-slate-100, .text-slate-200, .text-slate-300, .text-white { color: #0f172a !important; }
    .text-slate-400 { color: #475569 !important; }
    .text-slate-500 { color: #64748b !important; }
    .placeholder\:text-slate-600::placeholder { color: #94a3b8 !important; }
    .bg-blue-600.text-white, .bg-blue-700.text-white, .bg-emerald-700.text-white,
    .bg-rose-700.text-white, .bg-green-700.text-white { color: #fff !important; }
    .peaje-nav { position: sticky; top: .75rem; z-index: 30; border: 1px solid #e2e8f0; border-radius: 1rem; background: rgba(255,255,255,.94); padding: 1rem; box-shadow: 0 8px 30px rgba(15,23,42,.06); backdrop-filter: blur(12px); }
    .peaje-link { display:inline-flex; align-items:center; border-radius:.65rem; padding:.55rem .7rem; font-size:.8rem; font-weight:600; color:#475569; white-space:nowrap; transition:background .15s,color .15s; }
    .peaje-link:hover { background:#f1f5f9; color:#0f172a; }
    .peaje-link.active { background:#eff6ff; color:#1d4ed8; }
    .peaje-floating-agent { position:fixed; right:1.25rem; bottom:1.25rem; z-index:40; display:flex; align-items:center; gap:.6rem; border-radius:999px; background:#0f172a; padding:.85rem 1.1rem; color:#fff !important; box-shadow:0 12px 30px rgba(15,23,42,.28); font-size:.875rem; font-weight:700; }
    .peaje-floating-agent:hover { background:#1e293b; }
</style>
<header class="peaje-nav mb-7">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-600 text-lg font-bold text-white">P</span>
            <div>
                <a href="{{ route('dashboard') }}" class="text-lg font-bold tracking-tight text-slate-900">Peaje Inteligente</a>
                <p class="text-xs text-slate-500">Centro de control</p>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <div class="hidden text-right sm:block">
                <p class="max-w-44 truncate text-sm font-semibold text-slate-800">{{ auth()->user()->nombre }}</p>
                <p class="text-xs uppercase tracking-wide text-slate-500">{{ auth()->user()->rol === 'admin' ? 'Administrador' : 'Operador' }}</p>
            </div>
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">Salir</button>
            </form>
        </div>
    </div>
    <nav class="mt-4 flex flex-wrap gap-1 border-t border-slate-100 pt-3" aria-label="Navegación principal">
        <a href="{{ route('dashboard') }}" class="peaje-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">Panel en Vivo</a>
        <a href="{{ route('vehiculos.index') }}" class="peaje-link {{ request()->routeIs('vehiculos.*') ? 'active' : '' }}">Vehículos</a>
        <a href="{{ route('pasos.index') }}" class="peaje-link {{ request()->routeIs('pasos.*') ? 'active' : '' }}">Historial de Pasos</a>
        <a href="{{ route('alertas.index') }}" class="peaje-link {{ request()->routeIs('alertas.*') ? 'active' : '' }}">Alertas</a>
        <a href="{{ route('tarifas-casetas.index') }}" class="peaje-link {{ request()->routeIs('tarifas-casetas.*', 'tarifas.*', 'casetas.*') ? 'active' : '' }}">Tarifas y Casetas</a>
        @if(auth()->user()->rol === 'admin')
            <a href="{{ route('usuarios-oauth.index') }}" class="peaje-link {{ request()->routeIs('usuarios-oauth.*') ? 'active' : '' }}">Usuarios &amp; OAuth</a>
        @endif
        <a href="{{ route('agente.index') }}" class="peaje-link {{ request()->routeIs('agente.*') ? 'active' : '' }}">Agente IA</a>
        <a href="{{ route('estado-inferencia.index') }}" class="peaje-link {{ request()->routeIs('estado-inferencia.*', 'modelos.*') ? 'active' : '' }}">Inferencia &amp; Nube</a>
    </nav>
</header>
<a href="{{ route('agente.index') }}" class="peaje-floating-agent" aria-label="Abrir Agente IA Peaje">
    <span aria-hidden="true">✦</span><span>Agente IA Peaje</span>
</a>
