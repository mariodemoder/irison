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
            }
        } catch (\Throwable $e) {
            Log::error('Failed to send appointment cancelled notification', [
                'appointment_id' => $event->appointment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
