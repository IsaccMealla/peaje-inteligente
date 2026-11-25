<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Agente IA - Peaje Inteligente</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-950 p-4 text-slate-100 md:p-8">
<main class="mx-auto max-w-5xl">
    @include('layouts.navigation')
    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <header class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 p-5 md:p-6">
            <div class="flex items-center gap-3">
                <span class="flex h-11 w-11 items-center justify-center rounded-full bg-slate-900 text-lg text-white">✦</span>
                <div><p class="text-xs font-semibold uppercase tracking-wide text-blue-700">Asistente operativo</p><h1 class="text-xl font-bold">Agente IA Peaje</h1></div>
            </div>
            <form action="{{ route('agente.nueva-sesion') }}" method="POST">@csrf<button class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Nueva sesión</button></form>
        </header>
        @if(session('estado'))<p class="border-b border-emerald-100 bg-emerald-50 px-5 py-3 text-sm text-emerald-800">{{ session('estado') }}</p>@endif
        <div class="border-b border-slate-100 px-5 py-4">
            <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Consultas rápidas</p>
            <div class="flex flex-wrap gap-2">
                @foreach(['Tarifas del peaje', 'Resumen operativo', 'Alertas pendientes', 'Modelos M1–M4'] as $consulta)
                    <button type="button" data-consulta="{{ $consulta }}" class="consulta-rapida rounded-full border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 hover:border-blue-300 hover:bg-blue-50">{{ $consulta }}</button>
                @endforeach
            </div>
        </div>
        <div id="chat-mensajes" class="h-[50vh] min-h-80 space-y-4 overflow-y-auto bg-slate-50/70 p-5 md:p-6" aria-live="polite">
            @forelse($historial as $mensaje)
                <article class="flex {{ $mensaje['rol'] === 'user' ? 'justify-end' : 'justify-start' }}">
                    <div class="max-w-[90%] whitespace-pre-wrap rounded-2xl px-4 py-3 text-sm leading-6 {{ $mensaje['rol'] === 'user' ? 'bg-blue-600 text-white' : 'border border-slate-200 bg-white text-slate-700 shadow-sm' }}">
                        <p>{{ $mensaje['contenido'] }}</p><time class="mt-1 block text-right text-[10px] opacity-60">{{ $mensaje['hora'] }}</time>
                    </div>
                </article>
            @empty
                <article class="max-w-2xl rounded-2xl border border-slate-200 bg-white p-4 text-sm leading-6 text-slate-700 shadow-sm">
                    Hola, soy el asistente del sistema. Puedo consultar el padrón por placa, resumir alertas y tarifas, revisar modelos M1–M4 o analizar fotografías.
                    <p class="mt-2 text-xs text-slate-500">Estados de consulta: Registrada y con paso · Registrada sin paso · Identificada no registrada · No existe. Ante dudas OCR buscaré variantes O↔0, I↔1, B↔8 y S↔5.</p>
                </article>
            @endforelse
        </div>
        <div id="archivos-preview" class="hidden border-t border-slate-100 px-5 pt-4"></div>
        <form id="chat-form" class="border-t border-slate-100 bg-white p-4 md:p-5">
            @csrf
            <label class="sr-only" for="chat-entrada">Mensaje</label>
            <div class="flex items-end gap-2">
                <label class="flex h-11 w-11 shrink-0 cursor-pointer items-center justify-center rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50" title="Adjuntar hasta cinco fotografías">
                    <span aria-hidden="true">＋</span><input id="chat-imagenes" name="imagenes[]" type="file" accept="image/*" multiple class="sr-only">
                </label>
                <textarea id="chat-entrada" name="mensaje" rows="1" maxlength="1000" placeholder="Escribe una placa o consulta operativa…" class="max-h-32 min-h-11 flex-1 resize-y rounded-xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-blue-500"></textarea>
                <button id="chat-enviar" class="h-11 rounded-xl bg-slate-900 px-4 font-semibold text-white hover:bg-slate-800 disabled:opacity-50">Enviar</button>
            </div>
            <p class="mt-2 pl-1 text-[11px] text-slate-500">Puedes adjuntar hasta 5 imágenes. La consulta analiza la placa, categoría, ejes y pasajeros sin crear un paso.</p>
        </form>
    </section>
</main>
<script>
    const form = document.getElementById('chat-form');
    const mensajes = document.getElementById('chat-mensajes');
    const entrada = document.getElementById('chat-entrada');
    const archivos = document.getElementById('chat-imagenes');
    const preview = document.getElementById('archivos-preview');
    const urlChat = @json(route('agente.chat', [], false));
    const csrf = @json(csrf_token());
    let enviando = false;

    function dibujarMensaje(rol, contenido, hora = new Date().toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'})) {
        const fila = document.createElement('article');
        fila.className = `flex ${rol === 'user' ? 'justify-end' : 'justify-start'}`;
        const burbuja = document.createElement('div');
        burbuja.className = `max-w-[90%] whitespace-pre-wrap rounded-2xl px-4 py-3 text-sm leading-6 ${rol === 'user' ? 'bg-blue-600 text-white' : 'border border-slate-200 bg-white text-slate-700 shadow-sm'}`;
        const texto = document.createElement('p'); texto.textContent = contenido;
        const tiempo = document.createElement('time'); tiempo.className='mt-1 block text-right text-[10px] opacity-60'; tiempo.textContent=hora;
        burbuja.append(texto,tiempo); fila.append(burbuja); mensajes.append(fila);
        mensajes.scrollTop = mensajes.scrollHeight;
    }

    function actualizarPreview() {
        preview.replaceChildren();
        const seleccion = [...archivos.files];
        preview.classList.toggle('hidden', seleccion.length === 0);
        if (!seleccion.length) return;
        const fila = document.createElement('div'); fila.className='flex flex-wrap gap-3 pb-4';
        seleccion.slice(0,5).forEach((file) => {
            const caja = document.createElement('div'); caja.className='flex items-center gap-2 rounded-lg border border-slate-200 bg-white p-2 text-xs';
            const imagen = document.createElement('img'); imagen.src=URL.createObjectURL(file); imagen.alt=''; imagen.className='h-10 w-12 rounded object-cover';
            const nombre = document.createElement('span'); nombre.className='max-w-40 truncate'; nombre.textContent=file.name;
            caja.append(imagen,nombre); fila.append(caja);
        });
        preview.append(fila);
    }

    async function enviar(mensaje) {
        if (enviando) return;
        const imagenes = [...archivos.files].slice(0,5);
        const texto = mensaje ?? entrada.value.trim();
        if (!texto && !imagenes.length) return;
        enviando = true;
        document.getElementById('chat-enviar').disabled = true;
        dibujarMensaje('user', texto || `Analiza ${imagenes.length} fotografía(s)`);
        const datos = new FormData();
        datos.append('_token', csrf);
        datos.append('mensaje', texto);
        imagenes.forEach((imagen) => datos.append('imagenes[]', imagen));
        entrada.value = '';
        archivos.value = '';
        actualizarPreview();
        const escribiendo = document.createElement('p'); escribiendo.id='chat-escribiendo'; escribiendo.className='text-xs text-slate-500'; escribiendo.textContent='Analizando…'; mensajes.append(escribiendo);
        mensajes.scrollTop = mensajes.scrollHeight;
        try {
            const response = await fetch(urlChat, {method:'POST', headers:{Accept:'application/json'}, credentials:'same-origin', body:datos});
            const resultado = await response.json();
            if (!response.ok) throw new Error(resultado.mensaje || resultado.message || `HTTP ${response.status}`);
            document.getElementById('chat-escribiendo')?.remove();
            dibujarMensaje('assistant', resultado.respuesta);
        } catch (error) {
            document.getElementById('chat-escribiendo')?.remove();
            dibujarMensaje('assistant', `No pude completar la consulta: ${error.message}`);
        } finally {
            enviando = false;
            document.getElementById('chat-enviar').disabled = false;
            entrada.focus();
        }
    }

    form.addEventListener('submit', (event) => {event.preventDefault(); enviar();});
    archivos.addEventListener('change', actualizarPreview);
    document.querySelectorAll('.consulta-rapida').forEach((boton) => boton.addEventListener('click', () => enviar(boton.dataset.consulta)));
    entrada.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && !event.shiftKey) {event.preventDefault(); enviar();}
    });
    mensajes.scrollTop = mensajes.scrollHeight;
</script>
</body>
</html>
