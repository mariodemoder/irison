<?php

declare(strict_types=1);

namespace Modules\Notifications\Domain\Services;

use App\Models\Appointment;
use App\Events\AppointmentReminderSent;
use DomainException;
use Illuminate\Support\Facades\Log;
use Modules\Notifications\Domain\Contracts\ReminderRepositoryInterface;
use Modules\Notifications\Domain\Enums\NotificationStatus;
use Modules\Notifications\Domain\Enums\ReminderType;
use Throwable;

class ReminderDomainService
{
    public function __construct(
        private readonly ReminderRepositoryInterface $repository,
    ) {}

    public function sendAppointmentReminder(
        Appointment $appointment,
        ReminderType $reminderType,
        bool $markAppointmentSent = true,
        bool $throwOnFailure = false,
    ): array {
        $appointment->loadMissing(['patient', 'clinic']);

        $email = trim((string) $appointment->patient?->email);

        if ($email === '') {
            $log = $this->repository->create(
                clinicId: (int) $appointment->clinic_id,
                appointmentId: (int) $appointment->id,
                reminderType: $reminderType,
                recipientEmail: $email,
                status: NotificationStatus::Failed,
                errorMessage: 'Paciente sin email para enviar recordatorio.',
            );

            Log::warning('reminder.failed', [
                'event' => 'reminder.sent',
                'result' => 'failed',
                'appointment_id' => $appointment->id,
                'clinic_id' => (int) $appointment->clinic_id,
                'reminder_type' => $reminderType->value,
                'reason' => 'missing_email',
            ]);

            if ($throwOnFailure) {
                throw new DomainException('Paciente sin email para enviar recordatorio.');
            }

            return ['reminder' => $log, 'sent' => false];
        }

        try {
            $log = $this->repository->create(
                clinicId: (int) $appointment->clinic_id,
                appointmentId: (int) $appointment->id,
                reminderType: $reminderType,
                recipientEmail: $email,
                status: NotificationStatus::Queued,
                sentAt: now(),
            );

            if ($markAppointmentSent) {
                $column = $reminderType === ReminderType::TwentyFourHours ? 'reminder_24h_sent_at' : 'reminder_2h_sent_at';
                $appointment->$column = now();
                $appointment->save();
            }

            Log::info('reminder.queued', [
                'event' => 'reminder.queued',
                'result' => 'queued',
                'reminder_id' => $log->id,
                'appointment_id' => $appointment->id,
                'clinic_id' => (int) $appointment->clinic_id,
                'reminder_type' => $reminderType->value,
                'recipient_domain' => $this->extractEmailDomain($email),
            ]);

            AppointmentReminderSent::dispatch($appointment, $reminderType->value);

            return ['reminder' => $log, 'sent' => true];
        } catch (Throwable $e) {
            $log = $this->repository->create(
                clinicId: (int) $appointment->clinic_id,
                appointmentId: (int) $appointment->id,
                reminderType: $reminderType,
                recipientEmail: $email,
                status: NotificationStatus::Failed,
                errorMessage: $e->getMessage(),
            );

            Log::error('reminder.failed', [
                'event' => 'reminder.sent',
                'result' => 'failed',
                'reminder_id' => $log->id,
                'appointment_id' => $appointment->id,
                'clinic_id' => (int) $appointment->clinic_id,
                'reminder_type' => $reminderType->value,
                'recipient_domain' => $this->extractEmailDomain($email),
                'error_code' => class_basename($e),
            ]);

            if ($throwOnFailure) {
                throw new DomainException($e->getMessage());
            }

            return ['reminder' => $log, 'sent' => false];
        }
    }

    private function extractEmailDomain(string $email): ?string
    {
        $normalized = trim(strtolower($email));
        if ($normalized === '' || !str_contains($normalized, '@')) {
            return null;
        }
        return substr($normalized, strpos($normalized, '@') + 1) ?: null;
    }
}
