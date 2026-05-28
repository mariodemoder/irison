@extends('backoffice.layout')

@section('content')
    <div class="mb-6 flex items-end justify-between">
        <div>
            <h2 class="text-2xl font-semibold">Panel de operación SaaS</h2>
            <p class="text-sm text-slate-600">Sesión: {{ $admin?->name }} ({{ $admin?->role }})</p>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <article class="rounded-lg bg-white p-4 shadow-sm">
            <p class="text-sm text-slate-500">Clínicas totales</p>
            <p class="mt-2 text-3xl font-semibold">{{ $metrics['totalClinics'] }}</p>
        </article>
        <article class="rounded-lg bg-white p-4 shadow-sm">
            <p class="text-sm text-slate-500">Clínicas activas</p>
            <p class="mt-2 text-3xl font-semibold">{{ $metrics['activeClinics'] }}</p>
        </article>
        <article class="rounded-lg bg-white p-4 shadow-sm">
            <p class="text-sm text-slate-500">Trials por vencer (7d)</p>
            <p class="mt-2 text-3xl font-semibold">{{ $metrics['trialEndingSoon'] }}</p>
        </article>
        <article class="rounded-lg bg-white p-4 shadow-sm">
            <p class="text-sm text-slate-500">Past due</p>
            <p class="mt-2 text-3xl font-semibold">{{ $metrics['pastDueClinics'] }}</p>
        </article>
        <article class="rounded-lg bg-white p-4 shadow-sm">
            <p class="text-sm text-slate-500">Canceladas en gracia</p>
            <p class="mt-2 text-3xl font-semibold">{{ $metrics['canceledGrace'] }}</p>
        </article>
        <article class="rounded-lg bg-white p-4 shadow-sm">
            <p class="text-sm text-slate-500">Admins internos</p>
            <p class="mt-2 text-3xl font-semibold">{{ $metrics['internalAdmins'] }}</p>
        </article>
        <article class="rounded-lg bg-white p-4 shadow-sm">
            <p class="text-sm text-slate-500">Admins activos</p>
            <p class="mt-2 text-3xl font-semibold">{{ $metrics['activeInternalAdmins'] }}</p>
        </article>
    </div>
@endsection
