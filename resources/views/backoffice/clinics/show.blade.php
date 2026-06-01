@extends('backoffice.layout')

@section('content')
    @php
        $subscriptionStatus = strtolower((string) ($clinic->subscription_status ?? 'inactive'));
        $operationalStatus = strtolower((string) ($clinic->status ?? ''));
        $isGreenStatus = in_array($subscriptionStatus, ['trial', 'trial_warning', 'active'], true);
        $isRedStatus = in_array($subscriptionStatus, ['canceled', 'cancelled'], true)
            || in_array($operationalStatus, ['trial_read_only', 'churned'], true)
            || ! $isGreenStatus;
        $paidDaysLeft = $clinic->cancellationGraceDaysLeft();
    @endphp

    <div class="mb-4 flex items-center justify-between">
        <div>
            <h2 class="text-3xl font-semibold leading-tight">{{ $clinic->name }}</h2>
            <p class="mt-1 text-lg text-slate-700">{{ $clinic->email ?: 'Sin email de contacto' }}</p>
            <p class="text-base text-slate-600">
                Tenant #{{ $clinic->id }} · Estado:
                <span class="font-semibold {{ $isRedStatus ? 'text-rose-700' : 'text-emerald-700' }}">{{ $clinic->tenantStatus() }}</span>
            </p>
        </div>
        <div class="flex gap-2">
            @if (in_array(auth('admin')->user()?->role, ['super_admin', 'support'], true))
                <a class="rounded border border-slate-300 px-3 py-2 text-sm hover:bg-slate-50" href="{{ route('backoffice.clinics.edit', $clinic) }}">Editar</a>
            @endif
            <a class="rounded border border-slate-300 px-3 py-2 text-sm hover:bg-slate-50" href="{{ route('backoffice.clinics.index') }}">Volver</a>
        </div>
    </div>

    <div class="grid gap-4 md:grid-cols-2">
        <article class="rounded bg-white p-4 shadow-sm">
            <h3 class="text-lg font-medium">Datos</h3>
            <dl class="mt-3 space-y-2 text-sm">
                <div><dt class="text-slate-500">Nombre clínica</dt><dd class="text-lg font-semibold text-slate-900">{{ $clinic->name }}</dd></div>
                <div><dt class="text-slate-500">Email de contacto</dt><dd class="text-base text-slate-800">{{ $clinic->email ?: 'Sin email de contacto' }}</dd></div>
                <div><dt class="text-slate-500">Slug</dt><dd>{{ $clinic->slug ?: '-' }}</dd></div>
                <div><dt class="text-slate-500">Plan</dt><dd>{{ $clinic->plan ?: 'basic' }}</dd></div>
                <div><dt class="text-slate-500">Subscription status</dt><dd>{{ $clinic->subscription_status ?: '-' }}</dd></div>
                @if (in_array($subscriptionStatus, ['canceled', 'cancelled'], true))
                    <div>
                        <dt class="text-slate-500">Días pagos restantes</dt>
                        <dd class="font-semibold {{ ($paidDaysLeft ?? 0) > 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                            {{ $paidDaysLeft === null ? '0 días' : ($paidDaysLeft === 1 ? '1 día' : $paidDaysLeft . ' días') }}
                        </dd>
                    </div>
                @endif
                <div><dt class="text-slate-500">Trial ends at</dt><dd>{{ $clinic->trial_ends_at?->format('Y-m-d H:i') ?: '-' }}</dd></div>
                <div><dt class="text-slate-500">Suspended at</dt><dd>{{ $clinic->suspended_at?->format('Y-m-d H:i') ?: '-' }}</dd></div>
                <div><dt class="text-slate-500">Stripe customer</dt><dd>{{ $clinic->stripe_customer_id ?: $clinic->stripe_id ?: '-' }}</dd></div>
            </dl>
        </article>

        <article class="rounded bg-white p-4 shadow-sm">
            <h3 class="text-lg font-medium">Acciones administrativas</h3>

            @if (in_array(auth('admin')->user()?->role, ['super_admin', 'support'], true))
                <form class="mt-3 space-y-2 border-t border-slate-100 pt-3" method="POST" action="{{ route('backoffice.clinics.extend-trial', $clinic) }}">
                    @csrf
                    @method('PATCH')
                    <label class="block text-sm">Extender trial (días)
                        <input class="mt-1 w-full rounded border border-slate-300 px-2 py-1.5" name="days" type="number" min="1" max="60" value="7" required>
                    </label>
                    <label class="block text-sm">Motivo
                        <input class="mt-1 w-full rounded border border-slate-300 px-2 py-1.5" name="reason" type="text">
                    </label>
                    <button class="rounded bg-slate-900 px-3 py-1.5 text-sm text-white hover:bg-slate-700" type="submit">Extender trial</button>
                </form>

                <form class="mt-3 space-y-2 border-t border-slate-100 pt-3" method="POST" action="{{ route('backoffice.clinics.suspend', $clinic) }}">
                    @csrf
                    @method('PATCH')
                    <label class="block text-sm">Motivo suspensión
                        <input class="mt-1 w-full rounded border border-slate-300 px-2 py-1.5" name="reason" type="text">
                    </label>
                    <button class="rounded bg-rose-700 px-3 py-1.5 text-sm text-white hover:bg-rose-600" type="submit">Suspender</button>
                </form>

                <form class="mt-3 space-y-2 border-t border-slate-100 pt-3" method="POST" action="{{ route('backoffice.clinics.reactivate', $clinic) }}">
                    @csrf
                    @method('PATCH')
                    <label class="block text-sm">Motivo reactivación
                        <input class="mt-1 w-full rounded border border-slate-300 px-2 py-1.5" name="reason" type="text">
                    </label>
                    <button class="rounded bg-emerald-700 px-3 py-1.5 text-sm text-white hover:bg-emerald-600" type="submit">Reactivar</button>
                </form>
            @endif

            @if (in_array(auth('admin')->user()?->role, ['super_admin', 'billing'], true))
                <form class="mt-3 space-y-2 border-t border-slate-100 pt-3" method="POST" action="{{ route('backoffice.clinics.cancel-subscription', $clinic) }}">
                    @csrf
                    <label class="block text-sm">Motivo cancelación
                        <input class="mt-1 w-full rounded border border-slate-300 px-2 py-1.5" name="reason" type="text">
                    </label>
                    <button class="rounded bg-slate-900 px-3 py-1.5 text-sm text-white hover:bg-slate-700" type="submit">Cancelar suscripción</button>
                </form>

                <form class="mt-3 space-y-2 border-t border-slate-100 pt-3" method="POST" action="{{ route('backoffice.clinics.change-plan', $clinic) }}">
                    @csrf
                    @method('PATCH')
                    <label class="block text-sm">Plan
                        <select class="mt-1 w-full rounded border border-slate-300 px-2 py-1.5" name="plan" required>
                            @foreach (['basic', 'pro', 'enterprise'] as $plan)
                                <option value="{{ $plan }}" @selected(($clinic->plan ?: 'basic') === $plan)>{{ $plan }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block text-sm">Motivo
                        <input class="mt-1 w-full rounded border border-slate-300 px-2 py-1.5" name="reason" type="text">
                    </label>
                    <button class="rounded bg-cyan-700 px-3 py-1.5 text-sm text-white hover:bg-cyan-600" type="submit">Cambiar plan</button>
                </form>
            @endif

            @if (auth('admin')->user()?->role === 'super_admin')
                <form class="mt-3 border-t border-slate-100 pt-3" method="POST" action="{{ route('backoffice.clinics.impersonate', $clinic) }}">
                    @csrf
                    <button class="rounded bg-amber-600 px-3 py-1.5 text-sm text-white hover:bg-amber-500" type="submit">Login como clínica</button>
                </form>
            @endif
        </article>
    </div>

    <article class="mt-4 rounded bg-white p-4 shadow-sm">
        <h3 class="text-lg font-medium">Actividad</h3>
        <div class="mt-3 space-y-2 text-sm">
            @forelse ($activity as $row)
                <div class="rounded border border-slate-200 p-2">
                    <div class="flex items-center justify-between">
                        <strong>{{ $row->event }}</strong>
                        <span class="text-slate-500">{{ $row->created_at?->format('Y-m-d H:i:s') }}</span>
                    </div>
                    <div class="text-slate-600">Admin: {{ $row->adminUser?->email ?: '-' }} · Resultado: {{ $row->result }}</div>
                </div>
            @empty
                <p class="text-slate-500">Sin actividad registrada.</p>
            @endforelse
        </div>
    </article>
@endsection
