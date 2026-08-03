<?php

declare(strict_types=1);

namespace Modules\Notifications\Infrastructure\Listeners;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Patient;
use App\Models\PatientConsent;
use App\Models\SubscriptionRequest;
use App\Models\User;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Log;
use Modules\Notifications\Domain\Enums\EmailCategory;
use Modules\Notifications\Infrastructure\Persistence\EmailLogEloquentModel;
use Symfony\Component\Mime\Address;

class LogSentMail
{
    public function handle(MessageSent $event): void
    {
        try {
            $original = $event->message;
            $to = $this->primaryRecipient($original->getTo());

            if ($to === null) {
                return;
            }

            $data = $event->data;
            $class = $data['__laravel_mailable'] ?? $data['__laravel_notification'] ?? null;
            $category = $this->resolveCategory($class, $data);

            EmailLogEloquentModel::create([
                'clinic_id' => $this->resolveClinicId($data),
                'patient_id' => $this->resolvePatientId($data),
                'appointment_id' => $this->resolveAppointmentId($data),
                'reminder_id' => $this->resolveReminderId($data),
                'category' => $category,
                'to_email' => $to,
                'from_email' => $this->primaryRecipient($original?->getFrom()),
                'subject' => $original?->getSubject(),
                'status' => 'sent',
                'sent_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('email_log.capture_failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function resolveCategory(?string $class, array $data): string
    {
        if ($class !== null) {
            $base = config('notifications.email_logs.categories.' . $class);

            if ($base === 'appointment_reminder') {
                $reminderType = $data['reminder_type'] ?? null;
                if ($reminderType === '24h' || (int) ($data['hoursBefore'] ?? 0) === 24) {
                    return EmailCategory::Reminder24h->value;
                }
                if ($reminderType === '2h' || (int) ($data['hoursBefore'] ?? 0) === 2) {
                    return EmailCategory::Reminder2h->value;
                }
            }

            if (is_string($base) && EmailCategory::tryFrom($base) !== null) {
                return $base;
            }
        }

        return EmailCategory::Generic->value;
    }

    private function resolveClinicId(array $data): ?int
    {
        if (($data['clinic'] ?? null) instanceof Clinic) {
            return (int) $data['clinic']->getKey();
        }
        if (is_numeric($data['clinicId'] ?? null)) {
            return (int) $data['clinicId'];
        }
        if (($data['appointment'] ?? null) instanceof Appointment && $data['appointment']->clinic_id) {
            return (int) $data['appointment']->clinic_id;
        }
        if (($data['consent'] ?? null) instanceof PatientConsent && $data['consent']->clinic_id) {
            return (int) $data['consent']->clinic_id;
        }
        if (($data['request'] ?? null) instanceof SubscriptionRequest && $data['request']->clinic_id) {
            return (int) $data['request']->clinic_id;
        }
        if (($data['patient'] ?? null) instanceof Patient && $data['patient']->clinic_id) {
            return (int) $data['patient']->clinic_id;
        }
        if (($data['user'] ?? null) instanceof User && $data['user']->clinic_id) {
            return (int) $data['user']->clinic_id;
        }

        return currentClinicId();
    }

    private function resolvePatientId(array $data): ?int
    {
        if (($data['patient'] ?? null) instanceof Patient) {
            return (int) $data['patient']->getKey();
        }
        if (($data['consent'] ?? null) instanceof PatientConsent && $data['consent']->patient_id) {
            return (int) $data['consent']->patient_id;
        }
        if (($data['appointment'] ?? null) instanceof Appointment && $data['appointment']->patient_id) {
            return (int) $data['appointment']->patient_id;
        }

        return null;
    }

    private function resolveAppointmentId(array $data): ?int
    {
        if (($data['appointment'] ?? null) instanceof Appointment) {
            return (int) $data['appointment']->getKey();
        }

        return null;
    }

    private function resolveReminderId(array $data): ?int
    {
        if (is_numeric($data['reminder_id'] ?? null)) {
            return (int) $data['reminder_id'];
        }

        return null;
    }

    private function primaryRecipient(?array $addresses): ?string
    {
        if (empty($addresses)) {
            return null;
        }

        $first = reset($addresses);

        return $first instanceof Address ? $first->getAddress() : null;
    }
}
