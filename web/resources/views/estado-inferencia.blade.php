<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Inferencia &amp; Nube - Peaje Inteligente</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-950 p-4 text-slate-100 md:p-8">
<main class="mx-auto max-w-7xl">
    @include('layouts.navigation')
    <section class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-sm font-semibold uppercase tracking-[.18em] text-blue-600">Diagnóstico del sistema</p>
            <h1 class="mt-2 text-3xl font-bold">Inferencia &amp; Nube</h1>
            <p class="mt-1 text-slate-500">Estado de modelos, conectividad y análisis de una captura de prueba.</p>
        </div>
        <button id="comprobar-conexion" class="rounded-xl bg-blue-600 px-4 py-2.5 font-semibold text-white hover:bg-blue-700">Comprobar Conexión</button>
    </section>
    <section class="mb-6 grid gap-4 md:grid-cols-3">
        <article class="rounded-2xl border border-slate-200 bg-white p-5">
            <p class="text-sm text-slate-500">Estado del servicio</p>
            <p id="estado-general" class="mt-2 text-2xl font-bold">{{ collect($modelos)->every(fn ($modelo) => $modelo['estado'] === 'Operativo') ? 'En Línea' : 'Desconectado' }}</p>
            <p id="latencia-general" class="mt-1 text-sm text-slate-500">Verificación activa al comprobar conexión.</p>
        </article>
        <article class="rounded-2xl border border-slate-200 bg-white p-5">
            <p class="text-sm text-slate-500">URL configurada (INFERENCE_URL)</p>
            <p class="mt-2 break-all font-mono text-sm text-slate-800">{{ $inferenceUrl ?: 'No configurada' }}</p>
        </article>
        <article class="rounded-2xl border border-slate-200 bg-white p-5">
            <p class="text-sm text-slate-500">Base de Datos Relacional</p>
            <p class="mt-2 font-semibold text-emerald-700">Conectada a la aplicación</p>
            <p class="mt-1 text-sm text-slate-500">{{ $vehiculosTotal }} vehículos · {{ $usuariosTotal }} usuarios OAuth</p>
        </article>
    </section>
    <section class="mb-7 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach($modelos as $id => $modelo)
            <article id="modelo-{{ $id }}" class="rounded-2xl border border-slate-200 bg-white p-5">
                <div class="flex items-start justify-between gap-3">
                    <h2 class="font-semibold">{{ $modelo['nombre'] }}</h2>
                    <span class="modelo-badge rounded-full px-2.5 py-1 text-xs font-semibold {{ $modelo['estado'] === 'Operativo' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }}">{{ $modelo['estado'] }}</span>
                </div>
                <p class="modelo-latencia mt-4 text-sm text-slate-500">Latencia: {{ $modelo['latencia_ms'] === null ? '—' : $modelo['latencia_ms'].' ms' }}</p>
                <p class="mt-2 text-xs text-slate-400">{{ $id === 'm1' ? 'YOLOv9 + OCR' : ($id === 'm2' ? 'Segmentación de vehículos' : ($id === 'm3' ? 'Keypoints de ruedas' : 'Segmentación de pasajeros')) }}</p>
            </article>
        @endforeach
    </section>
    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="mb-5">
            <p class="text-sm font-semibold uppercase tracking-wide text-blue-600">Prueba manual</p>
            <h2 class="mt-1 text-xl font-bold">Análisis Manual por Fotografía</h2>
            <p class="mt-1 text-sm text-slate-500">Sube una imagen para ejecutar M1, M2, M3 y M4 sin usar la aplicación móvil.</p>
        </div>
        <form id="form-analizar" class="flex flex-col gap-3 sm:flex-row sm:items-end">
            @csrf
            <label class="flex-1 text-sm font-medium text-slate-700">Fotografía del vehículo
                <input name="imagen" type="file" accept="image/*" required class="mt-1.5 block w-full rounded-xl border border-slate-200 bg-white p-2 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2">
            </label>
            <button id="boton-analizar" class="rounded-xl bg-slate-900 px-5 py-3 font-semibold text-white hover:bg-slate-800">Analizar fotografía</button>
        </form>
        <div id="resultado-analisis" class="mt-5 hidden rounded-xl border border-slate-200 bg-slate-50 p-5" role="status" aria-live="polite"></div>
    </section>
</main>
<script>
    const urlComprobar = @json(route('estado-inferencia.comprobar', [], false));
    const urlAnalizar = @json(route('estado-inferencia.analizar', [], false));
    const tokenCsrf = @json(csrf_token());
    const nombresModelos = {m1:'M1 · YOLOv9 + OCR',m2:'M2 · Vehículos',m3:'M3 · Keypoints ruedas',m4:'M4 · Pasajeros'};
    document.getElementById('comprobar-conexion').addEventListener('click', async (event) => {
        const boton = event.currentTarget;
        boton.disabled = true;
        boton.textContent = 'Comprobando…';
        try {
            const inicio = performance.now();
            const response = await fetch(urlComprobar, {headers:{Accept:'application/json'}, credentials:'same-origin'});
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || `HTTP ${response.status}`);
            document.getElementById('estado-general').textContent = data.estado;
            document.getElementById('latencia-general').textContent = `Respuesta del servidor: ${Math.round(performance.now()-inicio)} ms`;
            Object.entries(data.modelos).forEach(([id, modelo]) => {
                const card = document.getElementById(`modelo-${id}`);
                if (!card) return;
                const badge = card.querySelector('.modelo-badge');
                badge.textContent = modelo.estado;
                badge.className = `modelo-badge rounded-full px-2.5 py-1 text-xs font-semibold ${modelo.estado === 'Operativo' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'}`;
                card.querySelector('.modelo-latencia').textContent = `Latencia: ${modelo.latencia_ms == null ? '—' : `${modelo.latencia_ms} ms`}`;
            });
        } catch (error) {
            document.getElementById('estado-general').textContent = 'Desconectado';
            document.getElementById('latencia-general').textContent = error.message;
        } finally {
            boton.disabled = false;
            boton.textContent = 'Comprobar Conexión';
        }
    });

    document.getElementById('form-analizar').addEventListener('submit', async (event) => {
        event.preventDefault();
        const boton = document.getElementById('boton-analizar');
        const salida = document.getElementById('resultado-analisis');
        boton.disabled = true;
        boton.textContent = 'Analizando…';
        salida.classList.remove('hidden');
        salida.textContent = 'Ejecutando los modelos de inferencia…';
        const datos = new FormData(event.currentTarget);
        datos.append('_token', tokenCsrf);
        try {
            const response = await fetch(urlAnalizar, {method:'POST', headers:{Accept:'application/json'}, credentials:'same-origin', body:datos});
            const data = await response.json();
            if (!response.ok) throw new Error(data.mensaje || data.message || `HTTP ${response.status}`);
            const resultado = data.resultado;
            salida.replaceChildren();
            const titulo = document.createElement('h3');
            titulo.className = 'text-lg font-bold';
            titulo.textContent = resultado.placa ? `Placa: ${resultado.placa}` : 'No se pudo leer la placa';
            salida.append(titulo);
            const lista = document.createElement('dl');
            lista.className = 'mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3';
            const valores = [
                ['Confianza OCR', `${Math.round(Number(resultado.confianza_ocr ?? resultado.confianza ?? 0)*100)}%`],
                ['Detecciones de placa', resultado.candidatos_placa ?? '—'],
                ['Confianza detector M1', `${Math.round(Number(resultado.confianza_detector_placa ?? 0)*100)}%`],
                ['Categoría (M2)', resultado.categoria ?? '—'],
                ['Ejes (M3)', resultado.ejes ?? '—'],
                ['Pasajeros (M4)', resultado.pasajeros ?? '—'],
                ['Latencia total', `${resultado.latencia ?? data.latencia_solicitud_ms} ms`],
                ['Latencias M1–M4', Object.entries(resultado.latencia_modelos ?? {}).map(([m,v])=>`${m.toUpperCase()} ${v} ms`).join(' · ') || '—'],
            ];
            valores.forEach(([label, valor]) => {
                const bloque = document.createElement('div');
                bloque.className = 'rounded-lg bg-white p-3';
                const dt = document.createElement('dt'); dt.className='text-xs text-slate-500'; dt.textContent=label;
                const dd = document.createElement('dd'); dd.className='mt-1 font-semibold'; dd.textContent=valor;
                bloque.append(dt,dd); lista.append(bloque);
            });
            salida.append(lista);
        } catch (error) {
            salida.textContent = `Error: ${error.message}`;
        } finally {
            boton.disabled = false;
            boton.textContent = 'Analizar fotografía';
        }
    });
</script>
</body>
</html>
