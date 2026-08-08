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

    @php
        $sort = $filters['sort'] ?? 'id';
        $direction = $filters['direction'] ?? 'desc';
        $queryParams = request()->query();
    @endphp

    <div class="overflow-hidden rounded bg-white shadow-sm">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-100 text-left">
                <tr>
                    <th class="px-3 py-2">
                        <a href="{{ route('backoffice.clinics.index', array_merge($queryParams, ['sort' => 'id', 'direction' => $sort === 'id' && $direction === 'asc' ? 'desc' : 'asc'])) }}" class="flex items-center gap-1 text-inherit no-underline hover:text-blue-700">
                            ID @if($sort === 'id')<span>{!! $direction === 'asc' ? '&#9650;' : '&#9660;' !!}</span>@endif
                        </a>
                    </th>
                    <th class="px-3 py-2">
                        <a href="{{ route('backoffice.clinics.index', array_merge($queryParams, ['sort' => 'name', 'direction' => $sort === 'name' && $direction === 'asc' ? 'desc' : 'asc'])) }}" class="flex items-center gap-1 text-inherit no-underline hover:text-blue-700">
                            Clínica / Contacto @if($sort === 'name')<span>{!! $direction === 'asc' ? '&#9650;' : '&#9660;' !!}</span>@endif
                        </a>
                    </th>
                    <th class="px-3 py-2">
                        <a href="{{ route('backoffice.clinics.index', array_merge($queryParams, ['sort' => 'slug', 'direction' => $sort === 'slug' && $direction === 'asc' ? 'desc' : 'asc'])) }}" class="flex items-center gap-1 text-inherit no-underline hover:text-blue-700">
                            Slug @if($sort === 'slug')<span>{!! $direction === 'asc' ? '&#9650;' : '&#9660;' !!}</span>@endif
                        </a>
                    </th>
                    <th class="px-3 py-2">
                        <a href="{{ route('backoffice.clinics.index', array_merge($queryParams, ['sort' => 'plan', 'direction' => $sort === 'plan' && $direction === 'asc' ? 'desc' : 'asc'])) }}" class="flex items-center gap-1 text-inherit no-underline hover:text-blue-700">
                            Plan @if($sort === 'plan')<span>{!! $direction === 'asc' ? '&#9650;' : '&#9660;' !!}</span>@endif
                        </a>
                    </th>
                    <th class="px-3 py-2">
                        <a href="{{ route('backoffice.clinics.index', array_merge($queryParams, ['sort' => 'subscription_status', 'direction' => $sort === 'subscription_status' && $direction === 'asc' ? 'desc' : 'asc'])) }}" class="flex items-center gap-1 text-inherit no-underline hover:text-blue-700">
                            Estado SaaS @if($sort === 'subscription_status')<span>{!! $direction === 'asc' ? '&#9650;' : '&#9660;' !!}</span>@endif
                        </a>
                    </th>
                    <th class="px-3 py-2">
                        <a href="{{ route('backoffice.clinics.index', array_merge($queryParams, ['sort' => 'trial_ends_at', 'direction' => $sort === 'trial_ends_at' && $direction === 'asc' ? 'desc' : 'asc'])) }}" class="flex items-center gap-1 text-inherit no-underline hover:text-blue-700">
                            Trial ends @if($sort === 'trial_ends_at')<span>{!! $direction === 'asc' ? '&#9650;' : '&#9660;' !!}</span>@endif
                        </a>
                    </th>
                    <th class="px-3 py-2">
                        <a href="{{ route('backoffice.clinics.index', array_merge($queryParams, ['sort' => 'last_activity_at', 'direction' => $sort === 'last_activity_at' && $direction === 'asc' ? 'desc' : 'asc'])) }}" class="flex items-center gap-1 text-inherit no-underline hover:text-blue-700">
                            Última actividad @if($sort === 'last_activity_at')<span>{!! $direction === 'asc' ? '&#9650;' : '&#9660;' !!}</span>@endif
                        </a>
                    </th>
                    <th class="px-3 py-2">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($clinics as $clinic)
                    @php
                        $tenantStatus = $clinic->tenantStatus();
                        $badgeColor = $clinic->backofficeStatusColor();
                        $alertBadges = [
                            'backoffice_upgrade_requested' => ['label' => 'Upgrade pendiente', 'class' => 'bg-amber-100 text-amber-800'],
                            'backoffice_reactivation_requested' => ['label' => 'Reactivación pendiente', 'class' => 'bg-sky-100 text-sky-800'],
                            'trial_expired' => ['label' => 'Trial vencido', 'class' => 'bg-rose-100 text-rose-700'],
                            'trial_converted' => ['label' => 'Trial a pago', 'class' => 'bg-emerald-100 text-emerald-700'],
                            'subscription_cancelled' => ['label' => 'Susc. cancelada', 'class' => 'bg-rose-100 text-rose-700'],
                        ];
                        $backofficeAlerts = $clinic->backoffice_alerts ?? [];
                    @endphp
                    <tr class="border-t border-slate-100">
                        <td class="px-3 py-2">{{ $clinic->id }}</td>
                        <td class="px-3 py-2">
                            <div class="text-lg font-semibold leading-tight">
                                {{ $clinic->name }}
                                @foreach ($backofficeAlerts as $alertKey)
                                    @if (isset($alertBadges[$alertKey]))
                                        <span class="ml-2 inline-block rounded px-2 py-0.5 align-middle text-xs font-medium {{ $alertBadges[$alertKey]['class'] }}" title="{{ $alertBadges[$alertKey]['label'] }}">{{ $alertBadges[$alertKey]['label'] }}</span>
                                    @endif
                                @endforeach
                            </div>
                            <div class="mt-1 text-base text-slate-600">{{ $clinic->email ?: 'Sin email de contacto' }}</div>
                        </td>
                        <td class="px-3 py-2">{{ $clinic->slug ?: '-' }}</td>
                        <td class="px-3 py-2">{{ $clinic->plan ?: 'basic' }}</td>
                        <td class="px-3 py-2">
                            <span class="text-base font-semibold {{ $badgeColor === 'blue' ? 'text-blue-700' : ($badgeColor === 'red' ? 'text-rose-700' : 'text-emerald-700') }}">
                                {{ $tenantStatus }}
                            </span>
                        </td>
                        <td class="px-3 py-2">{{ $clinic->trial_ends_at?->format('Y-m-d H:i') ?: '-' }}</td>
                        <td class="px-3 py-2 text-sm text-slate-600" title="{{ $clinic->last_activity_at?->format('Y-m-d H:i') ?: '' }}">
                            {{ $clinic->last_activity_at?->diffForHumans() ?? '—' }}
                        </td>
                        <td class="px-3 py-2">
                            <a class="rounded border border-slate-300 px-2 py-1 hover:bg-slate-50" href="{{ route('backoffice.clinics.show', $clinic) }}">Ver</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td class="px-3 py-4 text-slate-500" colspan="8">No hay clínicas para mostrar.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $clinics->links() }}
    </div>
@endsection
