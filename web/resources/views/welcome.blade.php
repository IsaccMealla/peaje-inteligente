<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Peaje Inteligente - Acceso</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-900 text-white flex items-center justify-center min-h-screen">
    <div class="bg-slate-800 p-8 rounded-xl shadow-lg max-w-md w-full text-center border border-slate-700">
        <h1 class="text-2xl font-bold mb-2">Sistema de Peaje Inteligente</h1>
        <p class="text-slate-400 text-sm mb-6">Panel de monitoreo y control vehicular con IA</p>
        <a href="{{ route('google.redirect') }}"
           class="inline-flex items-center justify-center w-full bg-blue-600 hover:bg-blue-500 text-white font-semibold py-3 px-4 rounded-lg transition">
            Iniciar sesión con Google
        </a>
    </div>
</body>
</html>