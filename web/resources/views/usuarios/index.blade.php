<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Usuarios &amp; OAuth - Peaje Inteligente</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-950 p-4 text-slate-100 md:p-8">
<main class="mx-auto max-w-7xl">
    @include('layouts.navigation')

    <section class="mb-7 flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="mb-2 text-sm font-semibold uppercase tracking-[.18em] text-blue-400">Administración de acceso</p>
            <h1 class="text-3xl font-bold tracking-tight md:text-4xl">Usuarios y Lista Blanca (Google OAuth)</h1>
            <p class="mt-2 text-slate-400">Gestión de cuentas Google autorizadas, roles y control de acceso al sistema</p>
        </div>
        <button type="button" onclick="abrirModalCrear()" class="rounded-xl bg-blue-600 px-5 py-3 font-semibold shadow-lg shadow-blue-950/40 hover:bg-blue-500">
            + Autorizar Cuenta
        </button>
    </section>

    @if(session('estado'))
        <div class="mb-5 rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4 text-emerald-300" role="status">{{ session('estado') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-5 rounded-xl border border-rose-500/30 bg-rose-500/10 p-4 text-rose-300" role="alert">{{ $errors->first() }}</div>
    @endif

    <section class="mb-7 grid gap-4 md:grid-cols-3">
        <article class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
            <p class="text-sm text-slate-400">Cuentas en Lista Blanca</p>
            <p class="mt-3 text-3xl font-bold">{{ $totalCuentas }}</p>
            <p class="mt-2 text-xs text-slate-500">Cuentas Google registradas</p>
        </article>
        <article class="rounded-2xl border border-emerald-900/70 bg-slate-900 p-5">
            <p class="text-sm text-slate-400">Accesos Autorizados</p>
            <p class="mt-3 text-3xl font-bold text-emerald-300">{{ $accesosAutorizados }}</p>
            <span class="mt-2 inline-flex rounded-full bg-emerald-500/10 px-2.5 py-1 text-xs font-medium text-emerald-300">Activos</span>
        </article>
        <article class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
            <p class="text-sm text-slate-400">Distribución de Roles</p>
            <div class="mt-4 flex flex-wrap gap-2">
                <span class="rounded-full bg-violet-500/15 px-3 py-1.5 text-sm text-violet-300">Admin · {{ $totalAdmins }}</span>
                <span class="rounded-full bg-sky-500/15 px-3 py-1.5 text-sm text-sky-300">Operadores · {{ $totalOperadores }}</span>
            </div>
        </article>
    </section>

    <section class="overflow-hidden rounded-2xl border border-slate-800 bg-slate-900 shadow-xl">
        <div class="flex flex-col gap-3 border-b border-slate-800 p-4 md:flex-row">
            <label class="relative flex-1">
                <span class="sr-only">Buscar por nombre o correo</span>
                <span class="pointer-events-none absolute left-3 top-2.5 text-slate-500">⌕</span>
                <input id="filtro-busqueda" type="search" placeholder="Buscar por nombre o correo Google…" class="w-full rounded-xl border border-slate-700 bg-slate-950 py-2.5 pl-9 pr-3 outline-none focus:border-blue-500">
            </label>
            <label>
                <span class="sr-only">Filtrar por rol</span>
                <select id="filtro-rol" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2.5 md:w-44">
                    <option value="todos">Todos los roles</option>
                    <option value="admin">Admin</option>
                    <option value="operador">Operador</option>
                </select>
            </label>
            <label>
                <span class="sr-only">Filtrar por estado</span>
                <select id="filtro-estado" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2.5 md:w-48">
                    <option value="todos">Todos los estados</option>
                    <option value="autorizado">Autorizado</option>
                    <option value="revocado">Revocado</option>
                </select>
            </label>
        </div>
        <div class="flex items-center justify-between px-4 py-3 text-xs text-slate-500">
            <span>Los cambios de acceso se aplican en el siguiente inicio de sesión.</span>
            <span id="usuarios-conteo">{{ $usuarios->count() }} cuentas</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[1050px] text-left text-sm">
                <thead class="bg-slate-800/80 text-xs uppercase tracking-wide text-slate-400">
                    <tr>
                        <th class="px-4 py-3">Usuario</th>
                        <th class="px-4 py-3">Correo Google Autorizado</th>
                        <th class="px-4 py-3">Rol</th>
                        <th class="px-4 py-3">Acceso</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3">Acciones</th>
                    </tr>
                </thead>
                <tbody id="usuarios-tabla" class="divide-y divide-slate-800">
                    @forelse($usuarios as $usuario)
                        @php
                            $iniciales = collect(explode(' ', trim($usuario->nombre)))->filter()->take(2)->map(fn ($parte) => mb_substr($parte, 0, 1))->implode('');
                        @endphp
                        <tr class="usuario-fila hover:bg-slate-800/40"
                            data-nombre="{{ mb_strtolower($usuario->nombre) }}"
                            data-correo="{{ mb_strtolower($usuario->email_google) }}"
                            data-rol="{{ $usuario->rol }}"
                            data-estado="{{ $usuario->activo ? 'autorizado' : 'revocado' }}">
                            <td class="px-4 py-4">
                                <div class="flex items-center gap-3">
                                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-blue-500/15 font-semibold text-blue-300">{{ mb_strtoupper($iniciales) }}</span>
                                    <div>
                                        <p class="font-medium text-white">{{ $usuario->nombre }}</p>
                                        @if($usuario->id === auth()->id())
                                            <span class="mt-1 inline-flex rounded-full bg-blue-500/15 px-2 py-0.5 text-[10px] font-semibold uppercase text-blue-300">Tú</span>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-4 text-slate-300"><span class="mr-2 font-bold text-red-400">G</span>{{ $usuario->email_google }}</td>
                            <td class="px-4 py-4">
                                <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $usuario->rol === 'admin' ? 'bg-violet-500/15 text-violet-300' : 'bg-sky-500/15 text-sky-300' }}">
                                    {{ $usuario->rol === 'admin' ? 'Administrador' : 'Operador' }}
                                </span>
                            </td>
                            <td class="px-4 py-4 text-xs text-slate-400">Exclusivo Google OAuth 2.0</td>
                            <td class="px-4 py-4">
                                <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $usuario->activo ? 'bg-emerald-500/10 text-emerald-300' : 'bg-rose-500/10 text-rose-300' }}">
                                    {{ $usuario->activo ? 'Autorizado' : 'Revocado' }}
                                </span>
                            </td>
                            <td class="px-4 py-4">
                                <div class="flex items-center gap-2">
                                    <button type="button"
                                        onclick="abrirModalEditar(this)"
                                        data-id="{{ $usuario->id }}"
                                        data-nombre="{{ $usuario->nombre }}"
                                        data-email="{{ $usuario->email_google }}"
                                        data-rol="{{ $usuario->rol }}"
                                        class="rounded-lg border border-slate-700 px-2.5 py-1.5 text-xs text-slate-200 hover:bg-slate-700">Editar</button>
                                    <form action="{{ route('usuarios-oauth.toggle', $usuario) }}" method="POST">
                                        @csrf @method('PATCH')
                                        <button type="submit" @disabled($usuario->id === auth()->id())
                                            class="rounded-lg border border-amber-500/30 px-2.5 py-1.5 text-xs text-amber-300 hover:bg-amber-500/10 disabled:cursor-not-allowed disabled:opacity-40"
                                            title="{{ $usuario->id === auth()->id() ? 'No puedes revocar tu propia cuenta' : '' }}">
                                            {{ $usuario->activo ? 'Revocar' : 'Reactivar' }}
                                        </button>
                                    </form>
                                    <form action="{{ route('usuarios-oauth.destroy', $usuario) }}" method="POST" onsubmit="return confirm('¿Eliminar esta cuenta de la lista blanca?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" @disabled($usuario->id === auth()->id())
                                            class="rounded-lg border border-rose-500/30 px-2.5 py-1.5 text-xs text-rose-300 hover:bg-rose-500/10 disabled:cursor-not-allowed disabled:opacity-40"
                                            title="{{ $usuario->id === auth()->id() ? 'No puedes eliminar tu propia cuenta' : '' }}">Eliminar</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr id="sin-usuarios"><td colspan="6" class="px-4 py-12 text-center text-slate-500">No hay cuentas en la lista blanca todavía.</td></tr>
                    @endforelse
                    <tr id="sin-resultados" class="hidden"><td colspan="6" class="px-4 py-12 text-center text-slate-500">No hay cuentas que coincidan con los filtros.</td></tr>
                </tbody>
            </table>
        </div>
    </section>
</main>

<div id="modal-usuario" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/80 p-4" role="dialog" aria-modal="true" aria-labelledby="modal-titulo">
    <div class="w-full max-w-lg rounded-2xl border border-slate-700 bg-slate-900 p-6 shadow-2xl">
        <div class="mb-5 flex items-start justify-between gap-4">
            <div>
                <h2 id="modal-titulo" class="text-xl font-bold">Autorizar cuenta</h2>
                <p id="modal-subtitulo" class="mt-1 text-sm text-slate-400">Añade una cuenta Google a la lista blanca.</p>
            </div>
            <button type="button" onclick="cerrarModal()" aria-label="Cerrar" class="rounded-lg px-2 py-1 text-xl text-slate-400 hover:bg-slate-800">×</button>
        </div>
        <form id="form-usuario" action="{{ route('usuarios-oauth.store') }}" method="POST" class="space-y-4">
            @csrf
            <input id="metodo-formulario" type="hidden" name="_method" value="POST">
            <label class="block text-sm font-medium text-slate-300">Nombre
                <input id="campo-nombre" name="nombre" required maxlength="255" class="mt-1.5 w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2.5 text-white outline-none focus:border-blue-500">
            </label>
            <label class="block text-sm font-medium text-slate-300">Correo Google
                <input id="campo-email" name="email_google" type="email" required maxlength="255" placeholder="usuario@dominio.com" class="mt-1.5 w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2.5 text-white outline-none focus:border-blue-500">
            </label>
            <label class="block text-sm font-medium text-slate-300">Rol
                <select id="campo-rol" name="rol" required class="mt-1.5 w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2.5 text-white">
                    <option value="operador">Operador</option>
                    <option value="admin">Administrador</option>
                </select>
            </label>
            <div class="flex justify-end gap-3 border-t border-slate-800 pt-4">
                <button type="button" onclick="cerrarModal()" class="rounded-xl border border-slate-700 px-4 py-2.5 text-slate-300 hover:bg-slate-800">Cancelar</button>
                <button id="boton-guardar" type="submit" class="rounded-xl bg-blue-600 px-4 py-2.5 font-semibold text-white hover:bg-blue-500">Autorizar cuenta</button>
            </div>
        </form>
    </div>
</div>

<script>
    const modalUsuario = document.getElementById('modal-usuario');
    const formUsuario = document.getElementById('form-usuario');
    const rutaActualizarBase = @json(url('/usuarios-oauth'));

    function abrirModalCrear() {
        formUsuario.action = @json(route('usuarios-oauth.store'));
        document.getElementById('metodo-formulario').value = 'POST';
        document.getElementById('modal-titulo').textContent = 'Autorizar cuenta';
        document.getElementById('modal-subtitulo').textContent = 'Añade una cuenta Google a la lista blanca.';
        document.getElementById('boton-guardar').textContent = 'Autorizar cuenta';
        document.getElementById('campo-nombre').value = '';
        document.getElementById('campo-email').value = '';
        document.getElementById('campo-rol').value = 'operador';
        mostrarModal();
    }

    function abrirModalEditar(boton) {
        formUsuario.action = `${rutaActualizarBase}/${encodeURIComponent(boton.dataset.id)}`;
        document.getElementById('metodo-formulario').value = 'PUT';
        document.getElementById('modal-titulo').textContent = 'Editar cuenta autorizada';
        document.getElementById('modal-subtitulo').textContent = 'Actualiza los datos y el rol de acceso.';
        document.getElementById('boton-guardar').textContent = 'Guardar cambios';
        document.getElementById('campo-nombre').value = boton.dataset.nombre;
        document.getElementById('campo-email').value = boton.dataset.email;
        document.getElementById('campo-rol').value = boton.dataset.rol;
        mostrarModal();
    }

    function mostrarModal() {
        modalUsuario.classList.remove('hidden');
        modalUsuario.classList.add('flex');
        document.getElementById('campo-nombre').focus();
    }

    function cerrarModal() {
        modalUsuario.classList.add('hidden');
        modalUsuario.classList.remove('flex');
    }

    modalUsuario.addEventListener('click', (evento) => {
        if (evento.target === modalUsuario) cerrarModal();
    });
    document.addEventListener('keydown', (evento) => {
        if (evento.key === 'Escape') cerrarModal();
    });

    const filasUsuario = [...document.querySelectorAll('.usuario-fila')];
    const filtroBusqueda = document.getElementById('filtro-busqueda');
    const filtroRol = document.getElementById('filtro-rol');
    const filtroEstado = document.getElementById('filtro-estado');
    const filaSinResultados = document.getElementById('sin-resultados');

    function filtrarUsuarios() {
        const busqueda = filtroBusqueda.value.trim().toLocaleLowerCase();
        let visibles = 0;
        filasUsuario.forEach((fila) => {
            const coincideTexto = `${fila.dataset.nombre} ${fila.dataset.correo}`.includes(busqueda);
            const coincideRol = filtroRol.value === 'todos' || fila.dataset.rol === filtroRol.value;
            const coincideEstado = filtroEstado.value === 'todos' || fila.dataset.estado === filtroEstado.value;
            const mostrar = coincideTexto && coincideRol && coincideEstado;
            fila.classList.toggle('hidden', !mostrar);
            if (mostrar) visibles++;
        });
        filaSinResultados.classList.toggle('hidden', visibles > 0 || filasUsuario.length === 0);
        document.getElementById('usuarios-conteo').textContent = `${visibles} ${visibles === 1 ? 'cuenta' : 'cuentas'}`;
    }

    filtroBusqueda.addEventListener('input', filtrarUsuarios);
    filtroRol.addEventListener('change', filtrarUsuarios);
    filtroEstado.addEventListener('change', filtrarUsuarios);
</script>
</body>
</html>
