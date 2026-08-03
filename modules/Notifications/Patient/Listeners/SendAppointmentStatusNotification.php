<?php

declare(strict_types=1);

namespace Modules\Notifications\Patient\Listeners;

use App\Events\AppointmentCancelled;
use App\Events\AppointmentCreated;
use App\Events\AppointmentUpdated;
use App\Models\Patient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Modules\Notifications\Domain\Enums\EmailCategory;
use Modules\Notifications\Infrastructure\Persistence\EmailLogEloquentModel;
use Modules\Notifications\Patient\Notifications\AppointmentCancelledNotification;
use Modules\Notifications\Patient\Notifications\AppointmentCreatedNotification;
use Modules\Notifications\Patient\Notifications\AppointmentUpdatedNotification;

class SendAppointmentStatusNotification implements ShouldQueue
{
    public function handleAppointmentCreated(AppointmentCreated $event): void
    {
        try {
            $appointment = $event->appointment->loadMissing(['patient', 'clinic']);
            $email = $appointment->patient?->email;

            if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                Notification::route('mail', $email)
                    ->notify(new AppointmentCreatedNotification($appointment));
            } else {
                $this->logFailed($appointment, EmailCategory::AppointmentCreated, $email);
            }
        } catch (\Throwable $e) {
            Log::error('Failed to send appointment created notification', [
                'appointment_id' => $event->appointment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function handleAppointmentUpdated(AppointmentUpdated $event): void
    {
        try {
            $appointment = $event->appointment->loadMissing(['patient', 'clinic']);
            $email = $appointment->patient?->email;

            if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                Notification::route('mail', $email)
                    ->notify(new AppointmentUpdatedNotification($appointment, $event->changedAttributes));
            } else {
                $this->logFailed($appointment, EmailCategory::AppointmentUpdated, $email);
            }
        } catch (\Throwable $e) {
            Log::error('Failed to send appointment updated notification', [
                'appointment_id' => $event->appointment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function handleAppointmentCancelled(AppointmentCancelled $event): void
    {
        try {
            $appointment = $event->appointment->loadMissing(['patient', 'clinic']);
            $email = $appointment->patient?->email;

            if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                Notification::route('mail', $email)
                    ->notify(new AppointmentCancelledNotification($appointment));
            } else {
                $this->logFailed($appointment, EmailCategory::AppointmentCancelled, $email);
            }
        } catch (\Throwable $e) {
            Log::error('Failed to send appointment cancelled notification', [
                'appointment_id' => $event->appointment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function logFailed($appointment, EmailCategory $category, ?string $email): void
    {
        EmailLogEloquentModel::create([
            'clinic_id' => $appointment->clinic_id,
            'patient_id' => $appointment->patient_id,
            'appointment_id' => $appointment->id,
            'category' => $category->value,
            'to_email' => $email,
            'status' => 'failed',
            'error_message' => 'Paciente sin email para enviar notificación.',
            'sent_at' => now(),
        ]);
    }
}
