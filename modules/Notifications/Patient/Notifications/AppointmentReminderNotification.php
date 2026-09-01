<?php

declare(strict_types=1);

namespace Modules\Notifications\Patient\Notifications;

use App\Models\Appointment;
use App\Models\Patient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Notifications\Domain\Enums\ReminderType;

class AppointmentReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Appointment $appointment,
        private readonly ReminderType $reminderType,
        private readonly ?int $reminderId = null,
    ) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $hoursBefore = $this->reminderType->hoursBefore();
        $clinic = $this->appointment->clinic;
        $patientName = $this->appointment->patient->first_name;

        return (new MailMessage)
            ->from(config('mail.from.address'), $clinic->name ?? config('mail.from.name'))
            ->subject("Recordatorio de cita - {$clinic->name}")
            ->view('emails.appointment-reminder', [
                'patientName' => $patientName,
                'clinic' => $clinic,
                'clinicName' => $clinic->name,
                'clinicPhone' => $clinic->phone,
                'dateText' => $this->appointment->start_time->format('d/m/Y'),
                'timeText' => $this->appointment->start_time->format('H:i'),
                'hoursBefore' => $hoursBefore,
                'clinicAddress' => $clinic->address ?? '',
                'reminder_type' => $this->reminderType->value,
                'reminder_id' => $this->reminderId,
            ]);
    }
}
