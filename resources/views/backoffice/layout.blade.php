<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Irison Backoffice</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon-backoffice.svg') }}">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="bg-slate-100 text-slate-900 min-h-screen">
    <header class="bg-slate-900 text-white">
        <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-3">
            <div>
                <p class="text-xs uppercase tracking-wide text-slate-300">Irison</p>
                <h1 class="text-lg font-semibold">Backoffice SaaS</h1>
            </div>

            @auth('admin')
                <nav class="flex items-center gap-4 text-sm">
                    <a class="hover:text-slate-200" href="{{ route('backoffice.dashboard') }}">Dashboard</a>
                    <a class="hover:text-slate-200" href="{{ route('backoffice.clinics.index') }}">Clínicas</a>
                    <a class="hover:text-slate-200" href="{{ route('backoffice.subscription-requests.index') }}">Upgrades</a>
                    <a class="hover:text-slate-200" href="{{ route('backoffice.admin-users.index') }}">Admin Users</a>
                    @include('backoffice.partials.notifications')
                    @if (session()->has('backoffice_impersonation'))
                        <form method="POST" action="{{ route('backoffice.impersonate.stop') }}">
                            @csrf
                            <button class="rounded bg-amber-600 px-3 py-1.5 hover:bg-amber-500" type="submit">Detener impersonate</button>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('backoffice.logout') }}">
                        @csrf
                        <button class="rounded bg-slate-700 px-3 py-1.5 hover:bg-slate-600" type="submit">Salir</button>
                    </form>
                </nav>
            @endauth
        </div>
    </header>

    <main class="mx-auto max-w-6xl px-4 py-6">
        @if (session('status'))
            <div class="mb-4 rounded border border-emerald-300 bg-emerald-50 px-4 py-3 text-emerald-700">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-4 rounded border border-rose-300 bg-rose-50 px-4 py-3 text-rose-700">
                <ul class="list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </main>
</body>
</html>
