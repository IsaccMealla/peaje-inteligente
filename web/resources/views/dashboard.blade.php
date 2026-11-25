<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel en Tiempo Real - Peaje Inteligente</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .capture { width: 120px; height: 80px; transition: transform .18s ease; transform-origin: left center; }
        .capture:hover { transform: scale(2.1); position: relative; z-index: 20; }
    </style>
</head>
<body class="min-h-screen bg-slate-950 p-4 text-slate-100 md:p-8">
    <main class="mx-auto max-w-7xl">
        @include('layouts.navigation')
        <section class="mb-7 flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[.2em] text-blue-400">Centro de control</p>
                <h1 class="mt-2 text-3xl font-bold text-white">Panel en Tiempo Real</h1>
                <p class="mt-1 text-slate-400">Actualización automática cada 2 segundos</p>
            </div>
            <span id="feed-status" class="rounded-full bg-slate-800 px-3 py-2 text-xs text-slate-300">Conectando…</span>
        </section>

        <div id="alerta-nueva" class="mb-5 hidden rounded-xl border border-amber-500/40 bg-amber-500/10 p-4 text-amber-200" role="status" aria-live="polite"></div>

        <section class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Indicadores del día">
            <article class="rounded-2xl border border-slate-800 bg-slate-900 p-5"><p class="text-sm text-slate-400">Recaudación del Día</p><p id="kpi-recaudacion" class="mt-3 text-3xl font-bold text-white">Bs. 0.00</p></article>
            <article class="rounded-2xl border border-slate-800 bg-slate-900 p-5"><p class="text-sm text-slate-400">Vehículos Registrados (Pasos)</p><p id="kpi-pasos" class="mt-3 text-3xl font-bold text-white">0</p></article>
            <article class="rounded-2xl border border-slate-800 bg-slate-900 p-5"><p class="text-sm text-slate-400">Alertas Activas</p><p id="kpi-alertas" class="mt-3 text-3xl font-bold text-amber-300">0</p></article>
            <article class="rounded-2xl border border-slate-800 bg-slate-900 p-5"><p class="text-sm text-slate-400">Pasajeros Detectados</p><p id="kpi-pasajeros" class="mt-3 text-3xl font-bold text-white">0</p></article>
        </section>

        <section class="mb-8">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-lg font-semibold text-white">Estado de modelos</h2>
                <a href="{{ route('modelos.index') }}" class="text-sm text-blue-400 hover:text-blue-300">Detalles</a>
            </div>
            <div id="modelos-live" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4"></div>
        </section>

        <section class="mb-8 overflow-hidden rounded-2xl border border-slate-800 bg-slate-900">
            <div class="flex items-center justify-between border-b border-slate-800 p-5">
                <div><h2 class="text-lg font-semibold text-white">Monitoreo de pasos</h2><p class="text-sm text-slate-400">Últimos registros de caseta</p></div>
                <a href="{{ route('pasos.index') }}" class="text-sm text-blue-400 hover:text-blue-300">Historial completo</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1050px] text-left text-sm">
                    <thead class="bg-slate-800/70 text-xs uppercase tracking-wide text-slate-400">
                        <tr><th class="p-4">Hora</th><th class="p-4">Captura</th><th class="p-4">Placa</th><th class="p-4">Categoría</th><th class="p-4">Ejes</th><th class="p-4">Pasajeros</th><th class="p-4">Tarifa</th><th class="p-4">Confianza</th><th class="p-4">Estado</th></tr>
                    </thead>
                    <tbody id="tabla-registros" class="divide-y divide-slate-800">
                        <tr><td colspan="9" class="p-8 text-center text-slate-500">Cargando registros…</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-800 bg-slate-900 p-6">
            <div class="mb-5"><h2 class="text-xl font-bold text-white">Agente Conversacional</h2><p class="mt-1 text-sm text-slate-400">Consulta por placa o envía una imagen para verificar el padrón y el historial.</p></div>
            <form id="agente-form" class="grid gap-4 md:grid-cols-[1fr_1fr_auto] md:items-end">
                @csrf
                <label class="text-sm text-slate-300">Placa
                    <input id="agente-texto" name="texto" type="text" maxlength="50" placeholder="Ej. 4021XYZ" class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white placeholder:text-slate-600">
                </label>
                <label class="text-sm text-slate-300">Foto del vehículo
                    <input id="agente-imagen" name="imagen" type="file" accept="image/*" class="mt-1 block w-full text-sm text-slate-300 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-700 file:px-3 file:py-2 file:text-white">
                </label>
                <button id="agente-enviar" type="submit" class="rounded-lg bg-blue-600 px-5 py-2.5 font-semibold text-white hover:bg-blue-500 disabled:opacity-50">Consultar</button>
            </form>
            <pre id="agente-respuesta" role="status" aria-live="polite" class="mt-4 hidden whitespace-pre-wrap rounded-xl border border-slate-800 bg-slate-950 p-4 text-sm text-slate-200"></pre>
        </section>
    </main>
    <script>
        const feedUrl = @json(route('dashboard.feed', [], false));
        const agenteUrl = @json(route('agente.consultar', [], false));
        const tabla = document.getElementById('tabla-registros');
        const notificacion = document.getElementById('alerta-nueva');
        const alertasVistas = new Set();
        let primeraCargaFeed = true;

        function el(tag, text, className = '') {
            const node = document.createElement(tag);
            node.textContent = text ?? '';
            node.className = className;
            return node;
        }

        function renderModelos(modelos) {
            const container = document.getElementById('modelos-live');
            container.replaceChildren();
            Object.values(modelos).forEach((modelo) => {
                const operativo = modelo.estado === 'Operativo';
                const card = el('article', '', 'rounded-xl border border-slate-800 bg-slate-900 p-4');
                const row = el('div', '', 'flex items-start justify-between gap-2');
                row.append(el('h3', modelo.nombre, 'text-sm font-semibold text-white'));
                row.append(el('span', modelo.estado, `shrink-0 rounded-full px-2 py-1 text-xs ${operativo ? 'bg-emerald-500/10 text-emerald-300' : 'bg-rose-500/10 text-rose-300'}`));
                card.append(row);
                card.append(el('p', modelo.latencia_ms == null ? 'Latencia: —' : `Latencia: ${modelo.latencia_ms} ms`, 'mt-3 text-xs text-slate-400'));
                container.append(card);
            });
        }

        function renderPasos(pasos) {
            tabla.replaceChildren();
            if (!pasos.length) {
                const row = document.createElement('tr');
                const cell = el('td', 'Aún no hay pasos registrados.', 'p-8 text-center text-slate-500');
                cell.colSpan = 9;
                row.append(cell);
                tabla.append(row);
                return;
            }
            pasos.forEach((paso) => {
                const row = document.createElement('tr');
                row.className = 'hover:bg-slate-800/40';
                row.append(el('td', paso.hora, 'whitespace-nowrap p-4 text-slate-400'));
                const captureCell = document.createElement('td');
                captureCell.className = 'p-4';
                const image = document.createElement('img');
                image.src = paso.captura_url;
                image.alt = `Captura de ${paso.placa}`;
                image.loading = 'lazy';
                image.className = 'capture cursor-zoom-in rounded-lg border border-slate-700 object-cover';
                captureCell.append(image);
                row.append(captureCell);
                row.append(el('td', paso.placa, 'p-4 font-mono font-bold text-amber-300'));
                row.append(el('td', paso.categoria, 'p-4 text-slate-200'));
                row.append(el('td', String(paso.ejes), 'p-4 text-slate-300'));
                row.append(el('td', String(paso.pasajeros), 'p-4 text-slate-300'));
                row.append(el('td', `Bs. ${Number(paso.tarifa).toFixed(2)}`, 'p-4 font-semibold text-white'));
                row.append(el('td', `${paso.confianza}%`, 'p-4 text-slate-300'));
                row.append(el('td', paso.estado, `p-4 ${paso.estado === 'procesado' ? 'text-emerald-300' : 'text-amber-300'}`));
                tabla.append(row);
            });
        }

        async function cargarFeed() {
            try {
                const response = await fetch(feedUrl, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
                if (response.status === 401 || response.redirected) throw new Error('Sesión expirada; vuelve a iniciar sesión.');
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                const data = await response.json();
                document.getElementById('feed-status').textContent = `Actualizado ${new Date().toLocaleTimeString()}`;
                document.getElementById('kpi-recaudacion').textContent = `Bs. ${Number(data.kpis.recaudacion_dia).toFixed(2)}`;
                document.getElementById('kpi-pasos').textContent = data.kpis.total_pasos;
                document.getElementById('kpi-alertas').textContent = data.kpis.alertas_activas;
                document.getElementById('kpi-pasajeros').textContent = data.kpis.pasajeros_detectados;
                renderModelos(data.modelos);
                renderPasos(data.pasos);
                data.alertas.forEach((alerta) => {
                    if (primeraCargaFeed) {
                        alertasVistas.add(alerta.id);
                    } else if (!alertasVistas.has(alerta.id)) {
                        alertasVistas.add(alerta.id);
                        notificacion.textContent = `Alerta ${alerta.tipo} (${alerta.hora}): ${alerta.mensaje}`;
                        notificacion.classList.remove('hidden');
                    }
                });
                primeraCargaFeed = false;
            } catch (error) {
                document.getElementById('feed-status').textContent = error.message;
                document.getElementById('feed-status').className = 'rounded-full bg-rose-500/10 px-3 py-2 text-xs text-rose-300';
            }
        }

        const agenteForm = document.getElementById('agente-form');
        const agenteEnviar = document.getElementById('agente-enviar');
        const agenteRespuesta = document.getElementById('agente-respuesta');
        agenteForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            agenteEnviar.disabled = true;
            agenteEnviar.textContent = 'Consultando…';
            agenteRespuesta.classList.remove('hidden');
            try {
                const response = await fetch(agenteUrl, { method: 'POST', headers: { Accept: 'application/json' }, credentials: 'same-origin', body: new FormData(agenteForm) });
                const data = await response.json();
                agenteRespuesta.textContent = data.mensaje ? `${data.estado ?? 'Consulta'}: ${data.mensaje}${data.sugerencias?.length ? `\nSugerencias: ${data.sugerencias.join(', ')}` : ''}` : `No se pudo completar la consulta (HTTP ${response.status}).`;
            } catch (error) {
                agenteRespuesta.textContent = `Error de consulta: ${error.message}`;
            } finally {
                agenteEnviar.disabled = false;
                agenteEnviar.textContent = 'Consultar';
            }
        });

        cargarFeed();
        window.setInterval(cargarFeed, 2000);
    </script>
</body>
</html>
