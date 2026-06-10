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

    <div class="mt-6 grid gap-4 lg:grid-cols-2">
        <article class="rounded-lg bg-white p-4 shadow-sm">
            <div class="mb-3 flex items-center justify-between">
                <h3 class="text-base font-semibold text-rose-700">Alertas Criticas</h3>
                <span class="text-sm text-slate-500">Pagos fallidos</span>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm whitespace-nowrap">
                    <thead class="border-b border-slate-200 bg-slate-50 text-left text-slate-600">
                        <tr>
                            <th class="px-3 py-2 font-medium">Clinica</th>
                            <th class="px-3 py-2 font-medium">Fallos</th>
                            <th class="px-3 py-2 font-medium">Ultimo fallo</th>
                            <th class="px-3 py-2 font-medium">Accion</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($failedPaymentAlerts as $alert)
                            <tr class="border-b border-slate-100 align-top last:border-b-0">
                                <td class="px-3 py-2 font-semibold text-slate-900">{{ $alert['clinic_name'] }}</td>
                                <td class="px-3 py-2 text-rose-700">{{ $alert['failed_count'] }}</td>
                                <td class="px-3 py-2 text-slate-700">{{ $alert['last_failed_at']?->format('Y-m-d H:i') ?: '-' }}</td>
                                <td class="px-3 py-2">
                                    <a class="rounded border border-slate-300 px-2 py-1 text-xs hover:bg-slate-50" href="{{ route('backoffice.clinics.show', $alert['clinic_id']) }}">Ver clinica</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="px-3 py-4 text-slate-500" colspan="4">Sin alertas criticas por pagos fallidos.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </article>

        <article class="rounded-lg bg-white p-4 shadow-sm">
            <div class="mb-3 flex items-center justify-between">
                <h3 class="text-base font-semibold text-amber-700">Alertas Warning</h3>
                <span class="text-sm text-slate-500">Trial por vencer (5 dias)</span>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm whitespace-nowrap">
                    <thead class="border-b border-slate-200 bg-slate-50 text-left text-slate-600">
                        <tr>
                            <th class="px-3 py-2 font-medium">Clinica</th>
                            <th class="px-3 py-2 font-medium">Estado</th>
                            <th class="px-3 py-2 font-medium">Trial ends at</th>
                            <th class="px-3 py-2 font-medium">Accion</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($trialEndingAlerts as $alert)
                            <tr class="border-b border-slate-100 align-top last:border-b-0">
                                <td class="px-3 py-2 font-semibold text-slate-900">{{ $alert->name }}</td>
                                <td class="px-3 py-2 text-slate-700">{{ $alert->subscription_status }}</td>
                                <td class="px-3 py-2 text-slate-700">{{ $alert->trial_ends_at?->format('Y-m-d H:i') ?: '-' }}</td>
                                <td class="px-3 py-2">
                                    <a class="rounded border border-slate-300 px-2 py-1 text-xs hover:bg-slate-50" href="{{ route('backoffice.clinics.show', $alert) }}">Ver clinica</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="px-3 py-4 text-slate-500" colspan="4">Sin alertas warning de trial por vencer.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </article>
    </div>
@endsection
