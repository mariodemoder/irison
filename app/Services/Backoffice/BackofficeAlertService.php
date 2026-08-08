<?php

declare(strict_types=1);

namespace App\Services\Backoffice;

use App\Models\AdminUser;
use App\Models\Clinic;
use App\Models\SubscriptionRequest;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Modules\Notifications\Backoffice\Notifications\BackofficeAlertNotification;

class BackofficeAlertService
{
    public function notify(string $type, Clinic $clinic, string $message, array $extra = []): void
    {
        try {
            $admins = AdminUser::query()->where('is_active', true)->get();

            if ($admins->isEmpty()) {
                return;
            }

            foreach ($admins as $admin) {
                $this->sendToAdmin($type, $clinic, $message, $extra, $admin);
            }
        } catch (\Throwable $e) {
            Log::error('Failed to send backoffice alert', [
                'type' => $type,
                'clinic_id' => (int) ($clinic->getKey() ?? 0),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Deriva las alertas de backoffice aplicables al estado actual de la clínica.
     *
     * @return array<int, string>
     */
    public function applicableAlertKeys(Clinic $clinic, ?Collection $pendingRequests = null): array
    {
        $keys = [];

        $subscriptionStatus = strtolower((string) ($clinic->subscription_status ?? 'inactive'));
        $operationalStatus = strtolower((string) ($clinic->status ?? ''));
        $trialEnded = $clinic->trial_ends_at !== null && now()->gte($clinic->trial_ends_at);

        $hasPendingUpgrade = (bool) ($clinic->has_pending_upgrade ?? false);
        if (! $hasPendingUpgrade && $pendingRequests !== null) {
            $hasPendingUpgrade = $pendingRequests->contains(static function ($request) use ($clinic): bool {
                return (int) $request->clinic_id === (int) $clinic->id
                    && ($request->type ?? SubscriptionRequest::TYPE_PLAN_CHANGE) === SubscriptionRequest::TYPE_PLAN_CHANGE;
            });
        }

        if ($hasPendingUpgrade) {
            $keys[] = 'backoffice_upgrade_requested';
        }

        $hasPendingReactivation = (bool) ($clinic->has_pending_reactivation ?? false);
        if (! $hasPendingReactivation && $pendingRequests !== null) {
            $hasPendingReactivation = $pendingRequests->contains(static function ($request) use ($clinic): bool {
                return (int) $request->clinic_id === (int) $clinic->id
                    && ($request->type ?? SubscriptionRequest::TYPE_PLAN_CHANGE) === SubscriptionRequest::TYPE_REACTIVATION;
            });
        }

        if ($hasPendingReactivation) {
            $keys[] = 'backoffice_reactivation_requested';
        }

        if ($trialEnded) {
            if (in_array($subscriptionStatus, ['trial', 'trial_warning', 'inactive'], true)
                || in_array($operationalStatus, ['trial_read_only', 'churned'], true)) {
                $keys[] = 'trial_expired';
            } elseif (in_array($subscriptionStatus, ['active', 'past_due', 'canceled', 'cancelled'], true)) {
                $keys[] = 'trial_converted';
            }
        }

        if (in_array($subscriptionStatus, ['canceled', 'cancelled'], true)) {
            $keys[] = 'subscription_cancelled';
        }

        return array_values(array_unique($keys));
    }

    /**
     * Crea las notificaciones de backoffice que falten para el estado actual de cada clínica
     * y adjunta en cada una la lista de alertas aplicables para la vista.
     *
     * @param Collection<int, Clinic> $clinics
     * @return Collection<int, Clinic>
     */
    public function reconcileMany(Collection $clinics): Collection
    {
        $admins = AdminUser::query()->where('is_active', true)->get();
        $adminIds = $admins->pluck('id');

        $existingKeys = collect();
        if ($adminIds->isNotEmpty()) {
            $existingKeys = DatabaseNotification::query()
                ->where('type', BackofficeAlertNotification::class)
                ->whereIn('notifiable_id', $adminIds)
                ->get(['notifiable_id', 'data'])
                ->map(static function (DatabaseNotification $notification): string {
                    $data = (array) $notification->data;

                    return ((string) ($data['type'] ?? ''))
                        .'|'.((int) ($data['clinic_id'] ?? 0))
                        .'|'.((int) $notification->notifiable_id);
                })
                ->flip();
        }

        $clinicIds = $clinics->pluck('id')->filter()->values();

        $pendingRequests = $clinicIds->isNotEmpty()
            ? SubscriptionRequest::query()
                ->whereIn('clinic_id', $clinicIds)
                ->where('status', 'pending')
                ->orderByDesc('id')
                ->get()
            : collect();

        foreach ($clinics as $clinic) {
            $keys = $this->applicableAlertKeys($clinic, $pendingRequests);
            $clinic->backoffice_alerts = $keys;

            foreach ($keys as $key) {
                foreach ($admins as $admin) {
                    $dedupe = $key.'|'.(int) $clinic->id.'|'.(int) $admin->id;
                    if (isset($existingKeys[$dedupe])) {
                        continue;
                    }

                    $this->notifyForKey($key, $clinic, $pendingRequests, $admin);
                }
            }
        }

        return $clinics;
    }

    public function upgradeRequested(Clinic $clinic, int $requestId, string $requestedPlan, ?string $requesterName = null): void
    {
        $this->notify(
            type: 'backoffice_upgrade_requested',
            clinic: $clinic,
            message: 'La clínica '.($clinic->name ?? '').' solicita un upgrade al plan '.$requestedPlan.'.',
            extra: [
                'request_id' => $requestId,
                'requested_plan' => $requestedPlan,
                'requester_name' => $requesterName ?? '',
            ],
        );
    }

    public function reactivationRequested(Clinic $clinic, int $requestId, ?string $requesterName = null, ?string $motive = null): void
    {
        $this->notify(
            type: 'backoffice_reactivation_requested',
            clinic: $clinic,
            message: 'La clínica '.($clinic->name ?? '').' solicita la reactivación de su cuenta.'
                .($motive !== null && trim($motive) !== '' ? ' Motivo: '.$motive.'.' : ''),
            extra: [
                'request_id' => $requestId,
                'requester_name' => $requesterName ?? '',
            ],
        );
    }

    public function trialConverted(Clinic $clinic): void
    {
        $this->notify(
            type: 'trial_converted',
            clinic: $clinic,
            message: 'La clínica '.($clinic->name ?? '').' ha pasado de trial a plan de pago ('.$clinic->plan.').',
            extra: [
                'plan' => (string) ($clinic->plan ?? 'basic'),
            ],
        );
    }

    public function trialExpired(Clinic $clinic): void
    {
        $this->notify(
            type: 'trial_expired',
            clinic: $clinic,
            message: 'El trial de la clínica '.($clinic->name ?? '').' ha vencido y ha pasado a modo solo lectura.',
        );
    }

    public function subscriptionCancelled(Clinic $clinic, ?string $reason = null): void
    {
        $this->notify(
            type: 'subscription_cancelled',
            clinic: $clinic,
            message: 'La clínica '.($clinic->name ?? '').' ha cancelado su suscripción.',
            extra: $reason !== null && trim($reason) !== '' ? ['reason' => $reason] : [],
        );
    }

    private function sendToAdmin(string $type, Clinic $clinic, string $message, array $extra, AdminUser $admin): void
    {
        $notification = new BackofficeAlertNotification(
            type: $type,
            clinicId: (int) $clinic->getKey(),
            clinicName: (string) ($clinic->name ?? ''),
            message: $message,
            extra: $extra,
        );

        $admin->notify($notification);
    }

    private function notifyForKey(string $key, Clinic $clinic, Collection $pendingRequests, AdminUser $admin): void
    {
        switch ($key) {
            case 'backoffice_upgrade_requested':
                $request = $pendingRequests->first(static function ($pending) use ($clinic): bool {
                    return (int) $pending->clinic_id === (int) $clinic->id
                        && ($pending->type ?? SubscriptionRequest::TYPE_PLAN_CHANGE) === SubscriptionRequest::TYPE_PLAN_CHANGE;
                });
                $this->sendToAdmin(
                    type: $key,
                    clinic: $clinic,
                    message: 'La clínica '.($clinic->name ?? '').' solicita un upgrade al plan '.($request->requested_plan ?? '').'.',
                    extra: [
                        'request_id' => (int) ($request->id ?? 0),
                        'requested_plan' => (string) ($request->requested_plan ?? ''),
                        'requester_name' => $request?->requester?->name ?? '',
                    ],
                    admin: $admin,
                );

                break;

            case 'backoffice_reactivation_requested':
                $request = $pendingRequests->first(static function ($pending) use ($clinic): bool {
                    return (int) $pending->clinic_id === (int) $clinic->id
                        && ($pending->type ?? SubscriptionRequest::TYPE_PLAN_CHANGE) === SubscriptionRequest::TYPE_REACTIVATION;
                });
                $this->sendToAdmin(
                    type: $key,
                    clinic: $clinic,
                    message: 'La clínica '.($clinic->name ?? '').' solicita la reactivación de su cuenta.'
                        .(($request?->comments ?? '') !== '' ? ' Motivo: '.$request->comments.'.' : ''),
                    extra: [
                        'request_id' => (int) ($request->id ?? 0),
                        'requester_name' => $request?->requester?->name ?? '',
                    ],
                    admin: $admin,
                );

                break;

            case 'trial_expired':
                $this->sendToAdmin(
                    type: $key,
                    clinic: $clinic,
                    message: 'El trial de la clínica '.($clinic->name ?? '').' ha vencido y ha pasado a modo solo lectura.',
                    extra: [],
                    admin: $admin,
                );

                break;

            case 'trial_converted':
                $this->sendToAdmin(
                    type: $key,
                    clinic: $clinic,
                    message: 'La clínica '.($clinic->name ?? '').' ha pasado de trial a plan de pago ('.$clinic->plan.').',
                    extra: [
                        'plan' => (string) ($clinic->plan ?? 'basic'),
                    ],
                    admin: $admin,
                );

                break;

            case 'subscription_cancelled':
                $this->sendToAdmin(
                    type: $key,
                    clinic: $clinic,
                    message: 'La clínica '.($clinic->name ?? '').' ha cancelado su suscripción.',
                    extra: [],
                    admin: $admin,
                );

                break;
        }
    }
}
