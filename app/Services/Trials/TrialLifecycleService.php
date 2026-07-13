<?php

declare(strict_types=1);

namespace App\Services\Trials;

use App\Mail\TrialLifecycleMail;
use App\Models\Clinic;
use App\Models\TrialJourneyEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class TrialLifecycleService
{
    private const DEFAULT_TRIAL_GRACE_DAYS = 7;

    public function process(?Carbon $now = null): array
    {
        $now = ($now ?? now())->copy();

        if (! Schema::hasTable('trial_journey_events')) {
            Log::warning('trial.lifecycle.skipped_missing_table', [
                'event' => 'trial.lifecycle.skipped_missing_table',
                'result' => 'skipped',
            ]);

            return [
                'processed' => 0,
                'emails_sent' => 0,
                'read_only_activated' => 0,
                'churn_marked' => 0,
            ];
        }

        $stats = [
            'processed' => 0,
            'emails_sent' => 0,
            'read_only_activated' => 0,
            'churn_marked' => 0,
        ];

        Clinic::query()
            ->whereNotNull('trial_ends_at')
            ->orderBy('id')
            ->chunkById(100, function ($clinics) use (&$stats, $now): void {
                foreach ($clinics as $clinic) {
                    $stats['processed']++;
                    $stats['emails_sent'] += $this->processMilestonesForClinic($clinic, $now);

                    if ($this->activateReadOnlyIfDue($clinic, $now)) {
                        $stats['read_only_activated']++;
                    }

                    if ($this->markChurnIfDue($clinic, $now)) {
                        $stats['churn_marked']++;
                    }
                }
            });

        return $stats;
    }

    private function processMilestonesForClinic(Clinic $clinic, Carbon $now): int
    {
        $status = strtolower((string) ($clinic->subscription_status ?? 'inactive'));
        $tenantStatus = strtolower((string) ($clinic->status ?? ''));

        if ($status === 'active' || in_array($tenantStatus, ['churned', 'trial_read_only', 'suspended'], true)) {
            return 0;
        }

        if (! in_array($status, ['trial', 'trial_warning', 'inactive'], true)) {
            return 0;
        }

        if (! $clinic->created_at) {
            return 0;
        }

        $days = (int) $clinic->created_at->copy()->startOfDay()->diffInDays($now->copy()->startOfDay());

        $map = [
            1 => 'trial_day_1',
            7 => 'trial_day_7',
            20 => 'trial_day_20',
            27 => 'trial_day_27',
            30 => 'trial_day_30',
        ];

        if (! array_key_exists($days, $map)) {
            return 0;
        }

        $eventKey = $map[$days];
        if ($this->wasEventAlreadySent($clinic, $eventKey)) {
            return 0;
        }

        [$subject, $headline, $message] = $this->milestoneCopy($days);
        $recipient = $this->resolveEmailRecipient($clinic);

        if ($recipient !== null) {
            Mail::to($recipient)->queue(new TrialLifecycleMail($clinic, $eventKey, $subject, $headline, $message));
        } else {
            Log::warning('trial.milestone.recipient_invalid', [
                'event' => 'trial.milestone.recipient_invalid',
                'result' => 'skipped',
                'clinic_id' => $clinic->id,
                'milestone' => $eventKey,
            ]);
        }

        $this->markEventAsSent($clinic, $eventKey, [
            'days' => $days,
            'recipient' => $recipient,
        ]);

        if (in_array($days, [20, 27], true) && $clinic->subscription_status === 'trial') {
            $clinic->subscription_status = 'trial_warning';
            $clinic->status = 'trial_warning';
            $clinic->save();
        }

        Log::info('trial.milestone.sent', [
            'event' => 'trial.milestone.sent',
            'result' => 'sent',
            'clinic_id' => $clinic->id,
            'milestone' => $eventKey,
            'recipient_domain' => $this->extractEmailDomain($recipient),
        ]);

        return 1;
    }

    private function activateReadOnlyIfDue(Clinic $clinic, Carbon $now): bool
    {
        $status = strtolower((string) ($clinic->subscription_status ?? 'inactive'));
        if (! in_array($status, ['trial', 'trial_warning'], true)) {
            return false;
        }

        if (! $clinic->trial_ends_at || $now->lessThan($clinic->trial_ends_at)) {
            return false;
        }

        if ((string) $clinic->status === 'trial_read_only') {
            return false;
        }

        $clinic->status = 'trial_read_only';
        $clinic->save();

        Log::info('trial.read_only_activated', [
            'event' => 'trial.read_only_activated',
            'result' => 'success',
            'clinic_id' => $clinic->id,
        ]);

        return true;
    }

    private function markChurnIfDue(Clinic $clinic, Carbon $now): bool
    {
        $tenantStatus = strtolower((string) ($clinic->status ?? ''));
        if ($tenantStatus !== 'trial_read_only') {
            return false;
        }

        if (! $clinic->trial_ends_at) {
            return false;
        }

        $churnAt = $clinic->trial_ends_at->copy()->addDays($this->trialGraceDays());
        if ($now->lessThan($churnAt)) {
            return false;
        }

        $clinic->status = 'churned';
        $clinic->subscription_status = 'inactive';
        $clinic->churned_at = $now->copy();
        $clinic->save();

        Log::info('trial.churned', [
            'event' => 'trial.churned',
            'result' => 'success',
            'clinic_id' => $clinic->id,
        ]);

        return true;
    }

    private function wasEventAlreadySent(Clinic $clinic, string $eventKey): bool
    {
        return TrialJourneyEvent::query()
            ->where('clinic_id', $clinic->id)
            ->where('event_key', $eventKey)
            ->exists();
    }

    private function markEventAsSent(Clinic $clinic, string $eventKey, array $payload = []): void
    {
        TrialJourneyEvent::query()->updateOrCreate(
            [
                'clinic_id' => $clinic->id,
                'event_key' => $eventKey,
            ],
            [
                'sent_at' => now(),
                'payload' => $payload,
            ]
        );
    }

    private function resolveEmailRecipient(Clinic $clinic): ?string
    {
        $owner = $clinic->ownerUser()->first();
        $email = trim((string) ($owner?->email ?: $clinic->email ?: ''));

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    private function milestoneCopy(int $days): array
    {
        return match ($days) {
            1 => [
                'Bienvenida a tu trial de Irison',
                'Bienvenida al trial',
                'Tu prueba ya está activa. Empieza creando tu configuración básica y agenda inicial.',
            ],
            7 => [
                'Tips para aprovechar Irison',
                'Onboarding de semana 1',
                'Aquí tienes recomendaciones para sacar más valor al flujo diario de tu clínica.',
            ],
            20 => [
                'Tu trial termina pronto',
                'Aviso de vencimiento próximo',
                'Te quedan pocos días de trial. Revisa opciones de conversión para no interrumpir tu operación.',
            ],
            27 => [
                'Últimos días para convertir tu trial',
                'CTA de conversión',
                'Estás en la recta final. Activa tu plan para mantener acceso completo sin interrupciones.',
            ],
            30 => [
                'Tu trial llegó al límite',
                'Suspensión parcial activada',
                'Tu clínica pasa a modo solo lectura. Activa tu plan para volver a operar con normalidad.',
            ],
            default => [
                'Actualización de trial',
                'Actualización de trial',
                'Revisa el estado de tu trial en Irison.',
            ],
        };
    }

    private function extractEmailDomain(?string $email): ?string
    {
        $normalized = trim(strtolower((string) $email));
        if ($normalized === '' || ! str_contains($normalized, '@')) {
            return null;
        }

        return substr($normalized, strpos($normalized, '@') + 1) ?: null;
    }

    private function trialGraceDays(): int
    {
        return max((int) config('billing.trial_grace_days', self::DEFAULT_TRIAL_GRACE_DAYS), 0);
    }
}
