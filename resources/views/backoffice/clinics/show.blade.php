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
        $canExtendTrial = in_array($subscriptionStatus, ['trial', 'trial_warning'], true);
        $canSuspend = ! $clinic->isSuspended();
        $canReactivate = $clinic->isSuspended() || in_array($subscriptionStatus, ['canceled', 'cancelled', 'inactive'], true);
        $canCancel = ! in_array($subscriptionStatus, ['canceled', 'cancelled'], true);
    @endphp

    @if ($errors->has('action'))
        <div class="mb-4 rounded border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
            {{ $errors->first('action') }}
        </div>
    @endif

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

    <div class="mb-4 rounded bg-white p-4 shadow-sm"
         x-data="{ open: false }">
        <div class="flex flex-wrap items-center gap-3">
            <div class="flex items-center gap-2">
                <span class="text-sm font-semibold uppercase tracking-wide text-slate-600">Plan:</span>
                <span class="rounded bg-slate-100 px-2 py-0.5 text-sm font-medium">{{ $clinic->plan ?: 'basic' }}</span>
                <span class="rounded px-2 py-0.5 text-sm font-semibold {{ $isRedStatus ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700' }}">
                    {{ $clinic->subscription_status ?: 'inactive' }}
                </span>
                @if (in_array($subscriptionStatus, ['canceled', 'cancelled'], true) && ($paidDaysLeft ?? 0) > 0)
                    <span class="text-xs text-slate-500">{{ $paidDaysLeft }} día(s) restantes</span>
                @endif
            </div>
            <div class="relative ml-auto">
                <button @click="open = !open"
                        class="flex items-center gap-1 rounded border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50">
                    Ir a sección
                    <svg x-bind:class="open ? 'rotate-180' : ''" class="h-4 w-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div x-show="open" @click.outside="open = false"
                     class="absolute right-0 z-10 mt-1 w-48 rounded border border-slate-200 bg-white py-1 shadow-lg">
                    <a @click="open = false" href="#datos" class="block px-4 py-2 text-sm hover:bg-slate-50">Datos</a>
                    <a @click="open = false" href="#usuarios" class="block px-4 py-2 text-sm hover:bg-slate-50">Usuarios</a>
                    <a @click="open = false" href="#administrativas" class="block px-4 py-2 text-sm hover:bg-slate-50">Administrativas</a>
                    <a @click="open = false" href="#desde-aqui" class="block px-4 py-2 text-sm hover:bg-slate-50">Desde aquí</a>
                    <a @click="open = false" href="#facturacion" class="block px-4 py-2 text-sm hover:bg-slate-50">Facturación</a>
                    <a @click="open = false" href="#notificaciones" class="block px-4 py-2 text-sm hover:bg-slate-50">Notificaciones</a>
                </div>
            </div>
        </div>
    </div>

    <div class="space-y-4">
            <div class="grid gap-4 md:grid-cols-2">
        <article id="datos" class="rounded bg-white p-4 shadow-sm">
            <h3 class="text-lg font-medium">Datos</h3>
            <dl class="mt-3 space-y-2 text-sm">
                <div><dt class="text-slate-500">Nombre clínica</dt><dd class="text-lg font-semibold text-slate-900">{{ $clinic->name }}</dd></div>
                <div><dt class="text-slate-500">Email de contacto</dt><dd class="text-base text-slate-800">{{ $clinic->email ?: 'Sin email de contacto' }}</dd></div>
                <div><dt class="text-slate-500">Slug</dt><dd>{{ $clinic->slug ?: '-' }}</dd></div>
                <div><dt class="text-slate-500">Plan</dt><dd>{{ $clinic->plan ?: 'basic' }}</dd></div>
                <div><dt class="text-slate-500">Límite usuarios</dt><dd>{{ $clinic->max_users }} usuarios</dd></div>
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
                <div><dt class="text-slate-500">Fecha de último pago Stripe</dt><dd>{{ $lastStripePaymentAt?->format('Y-m-d H:i') ?: '-' }}</dd></div>
                <div><dt class="text-slate-500">Fecha última actividad</dt><dd>{{ $lastClinicActivityAt?->format('Y-m-d H:i') ?: '-' }}</dd></div>
                <div><dt class="text-slate-500">Último login tenant</dt><dd>{{ $lastTenantLoginAt?->format('Y-m-d H:i') ?: '-' }}</dd></div>
                <div><dt class="text-slate-500">Último documento creado</dt><dd>{{ $lastDocumentCreatedAt?->format('Y-m-d H:i') ?: '-' }}</dd></div>
                <div><dt class="text-slate-500">Último error 500</dt><dd>{{ $last500ErrorAt?->format('Y-m-d H:i') ?: '-' }}</dd></div>
            </dl>
        </article>

        <article id="usuarios" class="rounded bg-white p-4 shadow-sm">
            <h3 class="text-lg font-medium">Usuarios ({{ $users->count() }})</h3>
            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full text-sm whitespace-nowrap">
                    <thead class="border-b border-slate-200 bg-slate-50 text-left text-slate-600">
                        <tr>
                            <th class="px-3 py-2 font-medium">Nombre</th>
                            <th class="px-3 py-2 font-medium">Cargo</th>
                            <th class="px-3 py-2 font-medium">Email</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($users as $user)
                            <tr class="border-b border-slate-100 align-top last:border-b-0">
                                <td class="px-3 py-2 font-medium text-slate-900">{{ $user->name }}</td>
                                <td class="px-3 py-2 text-slate-700">{{ $user->profile?->name ?: '-' }}</td>
                                <td class="px-3 py-2 text-slate-600">{{ $user->email }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td class="px-3 py-4 text-slate-500" colspan="3">Sin usuarios registrados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </article>

        <article id="administrativas" class="rounded bg-white p-4 shadow-sm">
            <h3 class="text-lg font-medium">Acciones administrativas</h3>

            @if (in_array(auth('admin')->user()?->role, ['super_admin', 'support'], true))
                <form class="mt-3 space-y-2 border-t border-slate-100 pt-3" method="POST" action="{{ route('backoffice.clinics.extend-trial', $clinic) }}">
                    @csrf
                    @method('PATCH')
                    <div class="flex flex-col gap-3 md:flex-row md:items-end">
                        <label class="block text-sm md:w-20">Días trial
                            <input class="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 text-center disabled:cursor-not-allowed disabled:bg-slate-100 md:w-16" name="days" type="number" min="1" max="60" value="7" required @disabled(! $canExtendTrial)>
                        </label>
                        <label class="block text-sm md:flex-1">Motivo
                            <input class="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 disabled:cursor-not-allowed disabled:bg-slate-100" name="reason" type="text" @disabled(! $canExtendTrial)>
                        </label>
                        <button class="rounded bg-slate-900 px-3 py-1.5 text-sm text-white hover:bg-slate-700 disabled:cursor-not-allowed disabled:bg-slate-300" type="submit" @disabled(! $canExtendTrial)>Extender trial</button>
                    </div>
                    @if (! $canExtendTrial)
                        <p class="text-xs text-slate-500">Solo disponible cuando la clínica está en trial.</p>
                    @endif
                </form>

                <form class="mt-3 space-y-2 border-t border-slate-100 pt-3" method="POST" action="{{ route('backoffice.clinics.suspend', $clinic) }}">
                    @csrf
                    @method('PATCH')
                    <div class="flex items-end gap-3">
                        <label class="block flex-1 text-sm">Motivo suspensión
                            <input class="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 disabled:cursor-not-allowed disabled:bg-slate-100" name="reason" type="text" @disabled(! $canSuspend)>
                        </label>
                        <button class="rounded bg-rose-700 px-3 py-1.5 text-sm text-white hover:bg-rose-600 disabled:cursor-not-allowed disabled:bg-slate-300" type="submit" @disabled(! $canSuspend)>Suspender</button>
                    </div>
                    @if (! $canSuspend)
                        <p class="text-xs text-slate-500">La clínica ya está suspendida.</p>
                    @endif
                </form>

                <form class="mt-3 space-y-2 border-t border-slate-100 pt-3" method="POST" action="{{ route('backoffice.clinics.reactivate', $clinic) }}">
                    @csrf
                    @method('PATCH')
                    <div class="flex items-end gap-3">
                        <label class="block flex-1 text-sm">Motivo reactivación
                            <input class="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 disabled:cursor-not-allowed disabled:bg-slate-100" name="reason" type="text" @disabled(! $canReactivate)>
                        </label>
                        <button class="rounded bg-emerald-700 px-3 py-1.5 text-sm text-white hover:bg-emerald-600 disabled:cursor-not-allowed disabled:bg-slate-300" type="submit" @disabled(! $canReactivate)>Reactivar</button>
                    </div>
                    @if (! $canReactivate)
                        <p class="text-xs text-slate-500">Solo se puede reactivar una clínica suspendida o cancelada.</p>
                    @endif
                </form>
            @endif

            @if (in_array(auth('admin')->user()?->role, ['super_admin', 'billing'], true))
                <form class="mt-3 space-y-2 border-t border-slate-100 pt-3" method="POST" action="{{ route('backoffice.clinics.cancel-subscription', $clinic) }}">
                    @csrf
                    <div class="flex items-end gap-3">
                        <label class="block flex-1 text-sm">Motivo cancelación
                            <input class="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 disabled:cursor-not-allowed disabled:bg-slate-100" name="reason" type="text" @disabled(! $canCancel)>
                        </label>
                        <button class="rounded bg-slate-900 px-3 py-1.5 text-sm text-white hover:bg-slate-700 disabled:cursor-not-allowed disabled:bg-slate-300" type="submit" @disabled(! $canCancel)>Cancelar suscripción</button>
                    </div>
                    @if (! $canCancel)
                        <p class="text-xs text-slate-500">La suscripción ya está cancelada.</p>
                    @endif
                </form>

                <form class="mt-3 space-y-2 border-t border-slate-100 pt-3" method="POST" action="{{ route('backoffice.clinics.change-plan', $clinic) }}">
                    @csrf
                    @method('PATCH')
                    <div class="flex flex-col gap-3 md:flex-row md:items-end">
                        <label class="block text-sm md:w-36">Plan
                            <select class="mt-1 w-full rounded border border-slate-300 px-2 py-1.5" name="plan" required>
                                @foreach (['basic', 'pro', 'enterprise'] as $plan)
                                    <option value="{{ $plan }}" @selected(($clinic->plan ?: 'basic') === $plan)>{{ $plan }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="block text-sm md:flex-1">Motivo
                            <input class="mt-1 w-full rounded border border-slate-300 px-2 py-1.5" name="reason" type="text">
                        </label>
                        <button class="rounded bg-cyan-700 px-3 py-1.5 text-sm text-white hover:bg-cyan-600" type="submit">Cambiar plan</button>
                    </div>
                </form>
            @endif
        </article>
    </div>

    <article id="desde-aqui" class="rounded bg-white p-4 shadow-sm">
        <h3 class="text-lg font-medium">Logs</h3>

        @if (auth('admin')->user()?->role === 'super_admin')
            <div class="mt-3 flex gap-3 border-t border-slate-100 pt-3">
                <form method="POST" action="{{ route('backoffice.clinics.impersonate', $clinic) }}">
                    @csrf
                    <button class="rounded bg-amber-600 px-3 py-1.5 text-sm text-white hover:bg-amber-500" type="submit">Login como clínica</button>
                </form>

                <form method="POST" action="{{ route('backoffice.clinics.clear-logs', $clinic) }}"
                      onsubmit="return confirm('¿Estás seguro de limpiar todos los logs de esta clínica? Esta acción no se puede deshacer.');">
                    @csrf
                    <button class="rounded bg-rose-700 px-3 py-1.5 text-sm text-white hover:bg-rose-600" type="submit">Limpiar logs</button>
                </form>
            </div>
        @endif

        <h4 class="mt-4 text-base font-medium text-slate-700">Logs de la clínica</h4>
        <div class="mt-3 overflow-x-auto">
            <table class="min-w-full text-sm whitespace-nowrap">
                <thead class="border-b border-slate-200 bg-slate-50 text-left text-slate-600">
                    <tr>
                        <th class="px-3 py-2 font-medium">Evento</th>
                        <th class="px-3 py-2 font-medium">Usuario</th>
                        <th class="px-3 py-2 font-medium">Fecha</th>
                        <th class="px-3 py-2 font-medium">Descripción</th>
                        <th class="px-3 py-2 font-medium">Detalle</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($activityLog as $row)
                        <tr class="border-b border-slate-100 align-top last:border-b-0">
                            <td class="px-3 py-2 font-semibold text-slate-900">{{ $row->event }}</td>
                            <td class="px-3 py-2 text-slate-600">{{ $row->user?->email ?: '-' }}</td>
                            <td class="px-3 py-2 text-slate-600">{{ $row->created_at?->format('Y-m-d H:i:s') }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ $row->description }}</td>
                            <td class="px-3 py-2 text-slate-600">{{ !empty($row->metadata) ? json_encode($row->metadata, JSON_UNESCAPED_UNICODE) : '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td class="px-3 py-4 text-slate-500" colspan="5">Sin actividad registrada.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </article>

    <article id="facturacion" class="rounded bg-white p-4 shadow-sm">
        <h3 class="text-lg font-medium">Facturación (Stripe)</h3>

        @if ($stripeInvoicesError)
            <div class="mt-3 rounded border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-700">
                {{ $stripeInvoicesError }}
            </div>
        @endif

        <div class="mt-3 overflow-x-auto">
            <table class="min-w-full text-sm whitespace-nowrap">
                <thead class="border-b border-slate-200 bg-slate-50 text-left text-slate-600">
                    <tr>
                        <th class="px-3 py-2 font-medium">Fecha</th>
                        <th class="px-3 py-2 font-medium">Invoice</th>
                        <th class="px-3 py-2 font-medium">Estado</th>
                        <th class="px-3 py-2 font-medium">Importe</th>
                        <th class="px-3 py-2 font-medium">Pagado</th>
                        <th class="px-3 py-2 font-medium">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($stripeInvoices as $invoice)
                        <tr class="border-b border-slate-100 align-top last:border-b-0">
                            <td class="px-3 py-2 text-slate-600">{{ $invoice['created_at']?->format('Y-m-d H:i') ?: '-' }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ $invoice['number'] ?: $invoice['id'] }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ $invoice['status'] ?: '-' }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ number_format(((int) $invoice['total']) / 100, 2, ',', '.') }} {{ $invoice['currency'] }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ number_format(((int) $invoice['amount_paid']) / 100, 2, ',', '.') }} {{ $invoice['currency'] }}</td>
                            <td class="px-3 py-2">
                                @if (! empty($invoice['hosted_invoice_url']))
                                    <a class="rounded border border-slate-300 px-2 py-1 text-xs hover:bg-slate-50" href="{{ $invoice['hosted_invoice_url'] }}" target="_blank" rel="noreferrer">Ver</a>
                                @elseif (! empty($invoice['invoice_pdf']))
                                    <a class="rounded border border-slate-300 px-2 py-1 text-xs hover:bg-slate-50" href="{{ $invoice['invoice_pdf'] }}" target="_blank" rel="noreferrer">PDF</a>
                                @else
                                    <span class="text-slate-400">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="px-3 py-4 text-slate-500" colspan="6">No hay facturas de Stripe registradas para esta clínica.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </article>

    <article id="notificaciones" class="rounded bg-white p-4 shadow-sm">
        <h3 class="text-lg font-medium">Notificaciones</h3>
        <div class="mt-3 overflow-x-auto">
            <table class="min-w-full text-sm whitespace-nowrap">
                <thead class="border-b border-slate-200 bg-slate-50 text-left text-slate-600">
                    <tr>
                        <th class="px-3 py-2 font-medium">Fecha y hora</th>
                        <th class="px-3 py-2 font-medium">Contacto</th>
                        <th class="px-3 py-2 font-medium">Teléfono</th>
                        <th class="px-3 py-2 font-medium">Email</th>
                        <th class="px-3 py-2 font-medium">Asunto</th>
                        <th class="px-3 py-2 font-medium">Cuerpo</th>
                        <th class="px-3 py-2 font-medium">Método</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($notifications as $notification)
                        <tr class="border-b border-slate-100 align-top last:border-b-0">
                            <td class="px-3 py-2 text-slate-600">{{ $notification['date_time']?->format('Y-m-d H:i:s') ?: '-' }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ $notification['contact_name'] }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ $notification['contact_phone'] }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ $notification['contact_email'] }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ $notification['subject'] }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ $notification['body'] }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ $notification['method'] === 'whatsapp' ? 'WhatsApp' : 'Email' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td class="px-3 py-4 text-slate-500" colspan="7">No hay notificaciones registradas para esta clínica.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </article>
        </div>
    </div>
@endsection
