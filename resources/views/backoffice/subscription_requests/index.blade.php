@extends('backoffice.layout')

@section('content')
<div class="rounded bg-white p-4 shadow-sm">
    <div class="mb-4 flex items-center justify-between">
        <h2 class="text-lg font-semibold">Solicitudes de upgrade</h2>

        <div class="flex gap-2">
            <a href="{{ route('backoffice.subscription-requests.index') }}"
               class="rounded bg-slate-100 px-3 py-1.5 text-sm {{ $currentStatus === '' ? 'font-bold text-slate-900' : 'text-slate-600 hover:bg-slate-200' }}">
                Todas
            </a>
            <a href="{{ route('backoffice.subscription-requests.index', ['status' => 'pending']) }}"
               class="rounded px-3 py-1.5 text-sm {{ $currentStatus === 'pending' ? 'bg-amber-100 font-bold text-amber-800' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                Pendientes
            </a>
            <a href="{{ route('backoffice.subscription-requests.index', ['status' => 'approved']) }}"
               class="rounded px-3 py-1.5 text-sm {{ $currentStatus === 'approved' ? 'bg-emerald-100 font-bold text-emerald-800' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                Aprobadas
            </a>
            <a href="{{ route('backoffice.subscription-requests.index', ['status' => 'rejected']) }}"
               class="rounded px-3 py-1.5 text-sm {{ $currentStatus === 'rejected' ? 'bg-rose-100 font-bold text-rose-800' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                Rechazadas
            </a>
        </div>
    </div>

    @if ($requests->isEmpty())
        <p class="py-6 text-center text-slate-500">No hay solicitudes.</p>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b text-left text-slate-500">
                        <th class="pb-2 pr-4">Cl&iacute;nica</th>
                        <th class="pb-2 pr-4">Plan actual</th>
                        <th class="pb-2 pr-4">Solicita</th>
                        <th class="pb-2 pr-4">Comentarios</th>
                        <th class="pb-2 pr-4">Solicitante</th>
                        <th class="pb-2 pr-4">Fecha</th>
                        <th class="pb-2 pr-4">Estado</th>
                        <th class="pb-2 pr-4">Acci&oacute;n</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($requests as $sr)
                        <tr class="border-b hover:bg-slate-50">
                            <td class="py-2 pr-4 font-medium">
                                <a href="{{ route('backoffice.clinics.show', $sr->clinic_id) }}" class="text-indigo-600 hover:underline">
                                    {{ $sr->clinic->name ?? '-' }}
                                </a>
                            </td>
                            <td class="py-2 pr-4 capitalize">{{ $sr->current_plan }}</td>
                            <td class="py-2 pr-4 capitalize">{{ $sr->requested_plan }}</td>
                            <td class="max-w-xs truncate py-2 pr-4 text-slate-500" title="{{ $sr->comments ?? '' }}">
                                {{ $sr->comments ?: '-' }}
                            </td>
                            <td class="py-2 pr-4">{{ $sr->requester->name ?? '-' }}</td>
                            <td class="whitespace-nowrap py-2 pr-4 text-slate-500">{{ $sr->created_at->format('d/m/Y H:i') }}</td>
                            <td class="py-2 pr-4">
                                @if ($sr->status === 'pending')
                                    <span class="rounded bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700">Pendiente</span>
                                @elseif ($sr->status === 'approved')
                                    <span class="rounded bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700">Aprobada</span>
                                @else
                                    <span class="rounded bg-rose-100 px-2 py-0.5 text-xs font-medium text-rose-700">Rechazada</span>
                                @endif
                            </td>
                            <td class="py-2">
                                @if ($sr->status === 'pending')
                                    <div x-data="{ open: false, action: 'approve' }" class="flex gap-1">
                                        <button @click="action = 'approve'; open = true"
                                                class="rounded bg-emerald-600 px-2 py-1 text-xs text-white hover:bg-emerald-500">
                                            Aprobar
                                        </button>
                                        <button @click="action = 'reject'; open = true"
                                                class="rounded bg-rose-600 px-2 py-1 text-xs text-white hover:bg-rose-500">
                                            Rechazar
                                        </button>

                                        <div x-show="open"
                                             x-cloak
                                             class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
                                             @click.away="open = false">
                                            <div class="w-full max-w-md rounded bg-white p-6 shadow-xl">
                                                <h3 class="mb-3 text-base font-semibold" x-text="action === 'approve' ? 'Aprobar solicitud' : 'Rechazar solicitud'"></h3>
                                                <p class="mb-3 text-sm text-slate-600">
                                                    {{ $sr->clinic->name ?? '-' }} solicita pasar de <strong>{{ $sr->current_plan }}</strong> a <strong>{{ $sr->requested_plan }}</strong>.
                                                </p>
                                                @if ($sr->comments)
                                                    <p class="mb-3 text-sm text-slate-500"><strong>Comentarios:</strong> {{ $sr->comments }}</p>
                                                @endif

                                                <form method="POST"
                                                      x-bind:action="action === 'approve' ? '{{ route('backoffice.subscription-requests.approve', $sr) }}' : '{{ route('backoffice.subscription-requests.reject', $sr) }}'">
                                                    @csrf
                                                    @method('PATCH')
                                                    <div class="mb-3">
                                                        <label class="block text-sm font-medium text-slate-700">Comentarios (opcional)</label>
                                                        <textarea name="reviewer_comments" rows="3"
                                                                  class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm"
                                                                  placeholder="Motivo de la decisi&oacute;n..."></textarea>
                                                    </div>
                                                    <div class="flex justify-end gap-2">
                                                        <button type="button" @click="open = false"
                                                                class="rounded border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50">
                                                            Cancelar
                                                        </button>
                                                        <button type="submit"
                                                                class="rounded px-3 py-1.5 text-sm text-white"
                                                                :class="action === 'approve' ? 'bg-emerald-600 hover:bg-emerald-500' : 'bg-rose-600 hover:bg-rose-500'">
                                                            <span x-text="action === 'approve' ? 'Confirmar aprobaci&oacute;n' : 'Confirmar rechazo'"></span>
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                @else
                                    <span class="text-xs text-slate-400">
                                        {{ $sr->reviewer?->name ?? '-' }}
                                        @if ($sr->reviewed_at)
                                            <br><span class="text-slate-300">{{ $sr->reviewed_at->format('d/m/Y') }}</span>
                                        @endif
                                    </span>
                                @endif
                            </td>
                        </tr>
                        @if ($sr->reviewer_comments)
                            <tr class="border-b bg-slate-50">
                                <td colspan="8" class="py-1 pl-4 text-xs text-slate-500">
                                    <strong>Comentarios del revisor:</strong> {{ $sr->reviewer_comments }}
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $requests->links() }}
        </div>
    @endif
</div>
@endsection
