<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Irison – Gestión</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}?v={{ @filemtime(public_path('favicon.svg')) }}">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}?v={{ @filemtime(public_path('favicon.ico')) }}">
    @vite('resources/js/app.js')
</head>
<body>
    <div id="app"></div>
</body>
</html>
