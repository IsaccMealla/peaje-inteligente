<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Panel en Vivo - Peaje Inteligente</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-950 p-4 text-slate-100 md:p-8">
<main class="mx-auto max-w-[1500px]">
    @include('layouts.navigation')
    <section class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-sm font-semibold uppercase tracking-[.18em] text-blue-600">Operación en tiempo real</p>
            <h1 class="mt-2 text-3xl font-bold tracking-tight">Panel en Vivo</h1>
            <p class="mt-1 text-slate-500">Tránsitos, evidencia y alertas de caseta; actualización automática cada 2 segundos.</p>
        </div>
        <span id="feed-status" class="rounded-full bg-white px-3 py-2 text-xs font-medium text-slate-500 shadow-sm">Conectando…</span>
    </section>
    <div id="alerta-nueva" class="mb-5 hidden rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800" role="status" aria-live="polite"></div>
    <section class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Indicadores">
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">Tránsitos de Hoy</p><p id="kpi-transitos" class="mt-3 text-3xl font-bold">0</p>
            <span id="kpi-transitos-badge" class="mt-2 inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs text-slate-600">0 acum. · 0 padrón</span>
        </article>
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">Recaudación Total</p><p id="kpi-recaudacion" class="mt-3 text-3xl font-bold">Bs. 0.00</p>
            <span class="mt-2 inline-flex rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700">Cobrado</span>
        </article>
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">Alertas Activas</p><p id="kpi-alertas" class="mt-3 text-3xl font-bold text-amber-600">0</p>
            <span class="mt-2 inline-flex rounded-full bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-700">Pendientes</span>
        </article>
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-start justify-between gap-2"><p class="text-sm text-slate-500">Infraestructura Nube</p><span id="kpi-nube-badge" class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">Conectando</span></div>
            <p id="kpi-nube-latencia" class="mt-3 text-2xl font-bold">Latencia —</p>
            <a href="{{ route('estado-inferencia.index') }}" class="mt-2 inline-block text-xs font-semibold text-blue-700">Ver diagnóstico →</a>
        </article>
    </section>

    <section class="grid items-start gap-5 xl:grid-cols-[minmax(0,1.85fr)_minmax(300px,1fr)]">
        <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 p-5">
                <div><h2 class="text-lg font-bold">Flujo de Vehículos en Tiempo Real</h2><p class="mt-1 text-sm text-slate-500">Últimas capturas procesadas por los modelos.</p></div>
                <a href="{{ route('pasos.index') }}" class="text-sm font-semibold text-blue-700">Historial completo →</a>
            </div>
            <div id="flujo-vehiculos" class="divide-y divide-slate-100">
                <p class="p-8 text-center text-sm text-slate-500">Cargando tránsitos…</p>
            </div>
        </article>
        <aside class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-100 p-5">
                <div><h2 class="text-lg font-bold">Alertas de Caseta</h2><p class="mt-1 text-sm text-slate-500">Requieren revisión del operador.</p></div>
                <a href="{{ route('alertas.index') }}" class="text-sm font-semibold text-blue-700">Ver todas</a>
            </div>
            <div id="alertas-caseta" class="divide-y divide-slate-100">
                <p class="p-6 text-center text-sm text-slate-500">Cargando alertas…</p>
            </div>
        </aside>
    </section>
</main>
<div id="modal-captura" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/80 p-4" role="dialog" aria-modal="true" aria-label="Evidencia de tránsito">
    <button id="cerrar-captura" class="absolute right-5 top-3 text-3xl text-white" aria-label="Cerrar">×</button>
    <img id="captura-ampliada" src="" alt="Evidencia ampliada" class="max-h-[90vh] max-w-[95vw] rounded-xl object-contain shadow-2xl">
</div>
<script>
    const feedUrl = @json(route('dashboard.feed', [], false));
    const csrf = @json(csrf_token());
    const flujo = document.getElementById('flujo-vehiculos');
    const listaAlertas = document.getElementById('alertas-caseta');
    const alertasVistas = new Set();
    let primeraCarga = true;
    let cargandoFeed = false;
    const el = (tag, text, classes = '') => {
        const node = document.createElement(tag);
        node.textContent = text ?? '';
        node.className = classes;
        return node;
    };

    function renderPasos(pasos) {
        flujo.replaceChildren();
        if (!pasos.length) {
            flujo.append(el('p', 'Aún no hay tránsitos registrados.', 'p-8 text-center text-sm text-slate-500'));
            return;
        }
        pasos.forEach((paso) => {
            const card = el('article', '', 'grid gap-4 p-4 sm:grid-cols-[200px_1fr] sm:p-5');
            const imagen = el('img', '', 'h-32 w-full cursor-zoom-in rounded-xl border border-slate-200 bg-slate-100 object-cover sm:h-36');
            imagen.src = paso.captura_url;
            imagen.alt = `Evidencia ${paso.placa}`;
            imagen.loading = 'lazy';
            imagen.addEventListener('click', () => {
                document.getElementById('captura-ampliada').src = paso.captura_url;
                document.getElementById('modal-captura').classList.remove('hidden');
                document.getElementById('modal-captura').classList.add('flex');
            });
            card.append(imagen);
            const contenido = el('div', '', 'min-w-0');
            const header = el('div', '', 'flex flex-wrap items-start justify-between gap-2');
            const placa = el('div', paso.placa, 'font-mono text-xl font-bold tracking-wide text-slate-900');
            header.append(placa);
            const badgeColor = paso.estado_operativo === 'Registrada y con paso' ? 'bg-emerald-50 text-emerald-700' :
                (paso.estado_operativo === 'Sin saldo' ? 'bg-amber-50 text-amber-700' : 'bg-rose-50 text-rose-700');
            header.append(el('span', paso.estado_operativo, `rounded-full px-2.5 py-1 text-xs font-semibold ${badgeColor}`));
            contenido.append(header);
            contenido.append(el('p', `Confianza OCR ${paso.confianza}% · ${paso.categoria} · ${paso.ejes} ejes`, 'mt-2 text-sm text-slate-600'));
            const badges = el('div', '', 'mt-3 flex flex-wrap items-center gap-2');
            badges.append(el('span', `M4 · ${paso.pasajeros} pasajeros`, 'rounded-lg bg-violet-50 px-2.5 py-1.5 text-xs font-medium text-violet-700'));
            badges.append(el('span', `Bs. ${Number(paso.tarifa).toFixed(2)}`, 'rounded-lg bg-blue-50 px-2.5 py-1.5 text-xs font-semibold text-blue-700'));
            contenido.append(badges);
            contenido.append(el('p', `${paso.hora}${paso.caseta ? ` · ${paso.caseta}` : ''}`, 'mt-3 text-xs text-slate-500'));
            card.append(contenido);
            flujo.append(card);
        });
    }

    function renderAlertas(alertas) {
        listaAlertas.replaceChildren();
        if (!alertas.length) {
            listaAlertas.append(el('p', 'No hay alertas pendientes.', 'p-6 text-center text-sm text-slate-500'));
            return;
        }
        alertas.forEach((alerta) => {
            const card = el('article', '', 'p-4');
            const meta = el('div', '', 'flex items-center justify-between gap-2');
            meta.append(el('span', alerta.tipo.replaceAll('_', ' ').toUpperCase(), 'rounded-full bg-amber-50 px-2 py-1 text-[10px] font-bold tracking-wide text-amber-700'));
            meta.append(el('span', alerta.hora, 'text-xs text-slate-500'));
            card.append(meta);
            card.append(el('p', alerta.mensaje, 'mt-2 text-sm leading-5 text-slate-700'));
            const button = el('button', 'Marcar Atendida', 'mt-3 rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50');
            button.type = 'button';
            button.addEventListener('click', async () => {
                button.disabled = true;
                try {
                    const response = await fetch(alerta.atender_url, {method:'PATCH', headers:{Accept:'application/json','X-CSRF-TOKEN':csrf,'X-Requested-With':'XMLHttpRequest'}, credentials:'same-origin'});
                    if (!response.ok) throw new Error(`No se pudo actualizar (HTTP ${response.status})`);
                    card.remove();
                    const actual = Number(document.getElementById('kpi-alertas').textContent);
                    document.getElementById('kpi-alertas').textContent = Math.max(0, actual - 1);
                    if (!listaAlertas.children.length) listaAlertas.append(el('p', 'No hay alertas pendientes.', 'p-6 text-center text-sm text-slate-500'));
                } catch (error) {
                    button.disabled = false;
                    button.textContent = error.message;
                }
            });
            card.append(button);
            listaAlertas.append(card);
        });
    }

    async function cargarFeed() {
        if (cargandoFeed) return;
        cargandoFeed = true;
        try {
            const response = await fetch(feedUrl, {credentials:'same-origin', headers:{Accept:'application/json'}});
            if (!response.ok) throw new Error(`Feed no disponible (HTTP ${response.status})`);
            const data = await response.json();
            document.getElementById('feed-status').textContent = `Actualizado ${new Date().toLocaleTimeString()}`;
            document.getElementById('kpi-transitos').textContent = data.kpis.total_pasos;
            document.getElementById('kpi-transitos-badge').textContent = `${data.kpis.total_acumulado} acum. · ${data.kpis.transitos_padron} padrón`;
            document.getElementById('kpi-recaudacion').textContent = `Bs. ${Number(data.kpis.recaudacion_dia).toFixed(2)}`;
            document.getElementById('kpi-alertas').textContent = data.kpis.alertas_activas;
            const nube = document.getElementById('kpi-nube-badge');
            nube.textContent = data.infraestructura.estado;
            nube.className = `rounded-full px-2.5 py-1 text-xs font-semibold ${data.infraestructura.estado === 'En Línea' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'}`;
            document.getElementById('kpi-nube-latencia').textContent = `Latencia ${data.infraestructura.latencia_ms == null ? '—' : `${data.infraestructura.latencia_ms} ms`}`;
            renderPasos(data.pasos);
            renderAlertas(data.alertas);
            data.alertas.forEach((alerta) => {
                if (!primeraCarga && !alertasVistas.has(alerta.id)) {
                    const aviso = document.getElementById('alerta-nueva');
                    aviso.textContent = `Nueva alerta (${alerta.hora}): ${alerta.mensaje}`;
                    aviso.classList.remove('hidden');
                    window.setTimeout(() => aviso.classList.add('hidden'), 7000);
                }
                alertasVistas.add(alerta.id);
            });
            primeraCarga = false;
        } catch (error) {
            document.getElementById('feed-status').textContent = error.message;
            document.getElementById('feed-status').className = 'rounded-full bg-rose-50 px-3 py-2 text-xs font-medium text-rose-700';
        } finally {
            cargandoFeed = false;
        }
    }

    const modal = document.getElementById('modal-captura');
    document.getElementById('cerrar-captura').addEventListener('click', () => {
        modal.classList.add('hidden'); modal.classList.remove('flex');
    });
    modal.addEventListener('click', (event) => {
        if (event.target === modal) { modal.classList.add('hidden'); modal.classList.remove('flex'); }
    });
    cargarFeed();
    window.setInterval(cargarFeed, 2000);
</script>
</body>
</html>
