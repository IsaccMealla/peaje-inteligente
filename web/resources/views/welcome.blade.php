<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Peaje Inteligente - Acceso</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="flex min-h-screen items-center justify-center bg-slate-50 p-4 text-slate-900">
    <div class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-8 text-center shadow-xl shadow-slate-200/60">
        <span class="mx-auto mb-5 flex h-14 w-14 items-center justify-center rounded-2xl bg-blue-600 text-2xl font-bold text-white">P</span>
        <h1 class="mb-2 text-2xl font-bold">Sistema de Peaje Inteligente</h1>
        <p class="mb-6 text-sm text-slate-500">Panel de monitoreo y control vehicular con IA</p>
        <a href="{{ route('google.redirect') }}"
           class="inline-flex w-full items-center justify-center rounded-xl bg-blue-600 px-4 py-3 font-semibold text-white transition hover:bg-blue-700">
            Iniciar sesión con Google
        </a>
    </div>
</body>
</html>