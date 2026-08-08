<div x-data="{ open: false }" class="relative">
    <button
        type="button"
        class="relative flex h-8 w-8 items-center justify-center rounded text-slate-300 hover:bg-slate-800 hover:text-white"
        aria-label="Notificaciones"
        @click="open = !open"
    >
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
        </svg>
        @if ($adminUnreadCount > 0)
            <span class="absolute -right-0.5 -top-0.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-rose-600 px-1 text-xs font-semibold text-white">
                {{ $adminUnreadCount > 9 ? '9+' : $adminUnreadCount }}
            </span>
        @endif
    </button>

    <div
        x-show="open"
        x-cloak
        @click.away="open = false"
        class="absolute right-0 top-full z-50 mt-2 w-96 max-w-[calc(100vw-2rem)] overflow-hidden rounded-lg bg-white text-slate-900 shadow-lg ring-1 ring-slate-200"
    >
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-2.5">
            <p class="text-sm font-semibold">Notificaciones</p>
            @if ($adminUnreadCount > 0)
                <form method="POST" action="{{ route('backoffice.notifications.read-all') }}">
                    @csrf
                    <button type="submit" class="text-xs text-indigo-600 hover:underline">Marcar todas como le&iacute;das</button>
                </form>
            @endif
        </div>

        <div class="max-h-80 overflow-y-auto">
            @forelse ($adminUnreadNotifications as $notification)
                @php
                    $data = (array) $notification->data;
                    $type = (string) ($data['type'] ?? '');
                    $clinicName = (string) ($data['clinic_name'] ?? '');
                    $clinicId = (int) ($data['clinic_id'] ?? 0);
                    $requestId = (int) ($data['request_id'] ?? 0);
                    $requestedPlan = (string) ($data['requested_plan'] ?? '');
                    $labels = [
                        'backoffice_upgrade_requested' => 'Upgrade solicitado',
                        'backoffice_reactivation_requested' => 'Reactivación solicitada',
                        'trial_converted' => 'Trial a pago',
                        'trial_expired' => 'Trial vencido',
                        'subscription_cancelled' => 'Suscripci&oacute;n cancelada',
                    ];
                    $label = $labels[$type] ?? 'Notificaci&oacute;n';
                @endphp
                <a
                    @if ($requestId > 0)
                        href="{{ route('backoffice.subscription-requests.index', ['status' => 'pending']) }}"
                    @elseif ($clinicId > 0)
                        href="{{ route('backoffice.clinics.show', $clinicId) }}"
                    @else
                        href="{{ route('backoffice.dashboard') }}"
                    @endif
                    @click="fetch('{{ route('backoffice.notifications.read', $notification) }}', { method: 'POST', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' } })"
                    class="block border-b border-slate-100 px-4 py-3 last:border-b-0 hover:bg-slate-50"
                >
                    <span class="mb-0.5 inline-block rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wide text-slate-600">{!! $label !!}</span>
                    <p class="text-sm font-medium">{{ $clinicName !== '' ? $clinicName : 'Cl&iacute;nica' }}</p>
                    @if ($requestedPlan !== '')
                        <p class="mt-0.5 text-xs text-slate-500">Upgrade a <span class="capitalize">{{ $requestedPlan }}</span></p>
                    @endif
                    <p class="mt-0.5 text-xs text-slate-500">{{ $data['message'] ?? '' }}</p>
                    <p class="mt-1 text-xs text-slate-400">{{ $notification->created_at?->diffForHumans() }}</p>
                </a>
            @empty
                <p class="px-4 py-6 text-center text-sm text-slate-500">No tienes notificaciones nuevas.</p>
            @endforelse
        </div>
    </div>
</div>
