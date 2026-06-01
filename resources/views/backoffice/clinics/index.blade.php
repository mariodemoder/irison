@extends('backoffice.layout')

@section('content')
    <div class="mb-4 flex items-center justify-between">
        <h2 class="text-2xl font-semibold">Clínicas (Tenants)</h2>
    </div>

    <form class="mb-4 grid gap-3 rounded bg-white p-4 shadow-sm md:grid-cols-4" method="GET" action="{{ route('backoffice.clinics.index') }}">
        <label class="text-sm">
            Buscar
            <input class="mt-1 w-full rounded border border-slate-300 px-2 py-1.5" name="q" type="text" value="{{ $filters['q'] }}" placeholder="nombre, slug o email">
        </label>
        <label class="text-sm">
            Estado
            <select class="mt-1 w-full rounded border border-slate-300 px-2 py-1.5" name="status">
                <option value="">Todos</option>
                @foreach (['trial', 'active', 'past_due', 'cancelled', 'suspended', 'expired'] as $status)
                    <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ $status }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-sm">
            Plan
            <select class="mt-1 w-full rounded border border-slate-300 px-2 py-1.5" name="plan">
                <option value="">Todos</option>
                @foreach (['basic', 'pro', 'enterprise'] as $plan)
                    <option value="{{ $plan }}" @selected($filters['plan'] === $plan)>{{ $plan }}</option>
                @endforeach
            </select>
        </label>
        <div class="flex items-end">
            <button class="rounded bg-slate-900 px-4 py-2 text-sm text-white hover:bg-slate-700" type="submit">Filtrar</button>
        </div>
    </form>

    <div class="overflow-hidden rounded bg-white shadow-sm">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-100 text-left">
                <tr>
                    <th class="px-3 py-2">ID</th>
                    <th class="px-3 py-2">Clínica / Contacto</th>
                    <th class="px-3 py-2">Slug</th>
                    <th class="px-3 py-2">Plan</th>
                    <th class="px-3 py-2">Estado SaaS</th>
                    <th class="px-3 py-2">Trial ends</th>
                    <th class="px-3 py-2">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($clinics as $clinic)
                    @php
                        $subscriptionStatus = strtolower((string) ($clinic->subscription_status ?? 'inactive'));
                        $operationalStatus = strtolower((string) ($clinic->status ?? ''));
                        $isGreenStatus = in_array($subscriptionStatus, ['trial', 'trial_warning', 'active'], true);
                        $isRedStatus = in_array($subscriptionStatus, ['canceled', 'cancelled'], true)
                            || in_array($operationalStatus, ['trial_read_only', 'churned'], true)
                            || ! $isGreenStatus;
                    @endphp
                    <tr class="border-t border-slate-100">
                        <td class="px-3 py-2">{{ $clinic->id }}</td>
                        <td class="px-3 py-2">
                            <div class="text-lg font-semibold leading-tight">{{ $clinic->name }}</div>
                            <div class="mt-1 text-base text-slate-600">{{ $clinic->email ?: 'Sin email de contacto' }}</div>
                        </td>
                        <td class="px-3 py-2">{{ $clinic->slug ?: '-' }}</td>
                        <td class="px-3 py-2">{{ $clinic->plan ?: 'basic' }}</td>
                        <td class="px-3 py-2">
                            <span class="text-base font-semibold {{ $isRedStatus ? 'text-rose-700' : 'text-emerald-700' }}">
                                {{ $clinic->tenantStatus() }}
                            </span>
                        </td>
                        <td class="px-3 py-2">{{ $clinic->trial_ends_at?->format('Y-m-d H:i') ?: '-' }}</td>
                        <td class="px-3 py-2">
                            <a class="rounded border border-slate-300 px-2 py-1 hover:bg-slate-50" href="{{ route('backoffice.clinics.show', $clinic) }}">Ver</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td class="px-3 py-4 text-slate-500" colspan="7">No hay clínicas para mostrar.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $clinics->links() }}
    </div>
@endsection
