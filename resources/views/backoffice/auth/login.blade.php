<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Backoffice Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-950 text-slate-100">
    <main class="mx-auto flex min-h-screen max-w-md items-center px-4">
        <section class="w-full rounded-2xl border border-slate-800 bg-slate-900 p-8 shadow-2xl">
            <p class="text-xs uppercase tracking-wider text-slate-400">Irison</p>
            <h1 class="mt-1 text-2xl font-semibold">Acceso Backoffice</h1>
            <p class="mt-2 text-sm text-slate-400">Solo uso interno para staff.</p>

            @if ($errors->any())
                <div class="mt-4 rounded border border-rose-700 bg-rose-950/50 px-3 py-2 text-sm text-rose-200">
                    {{ $errors->first() }}
                </div>
            @endif

            <form class="mt-6 space-y-4" method="POST" action="{{ route('backoffice.login.store') }}">
                @csrf
                <div>
                    <label class="mb-1 block text-sm text-slate-300" for="email">Email</label>
                    <input class="w-full rounded border border-slate-700 bg-slate-950 px-3 py-2" id="email" name="email" type="email" value="{{ old('email') }}" required>
                </div>

                <div>
                    <label class="mb-1 block text-sm text-slate-300" for="password">Contraseña</label>
                    <input class="w-full rounded border border-slate-700 bg-slate-950 px-3 py-2" id="password" name="password" type="password" required>
                </div>

                <label class="flex items-center gap-2 text-sm text-slate-300">
                    <input name="remember" type="checkbox" value="1"> Recuérdame
                </label>

                <button class="w-full rounded bg-cyan-500 px-4 py-2 font-medium text-slate-950 hover:bg-cyan-400" type="submit">
                    Iniciar sesión
                </button>
            </form>
        </section>
    </main>
</body>
</html>
