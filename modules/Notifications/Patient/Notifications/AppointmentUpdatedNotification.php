<?php

declare(strict_types=1);

namespace Modules\Notifications\Patient\Notifications;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AppointmentUpdatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Appointment $appointment,
        public readonly array $changedAttributes,
    ) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $clinic = $this->appointment->clinic;
        $patient = $this->appointment->patient;
        $changes = [];

        if (isset($this->changedAttributes['start_time'])) {
            $changes[] = 'fecha/hora';
        }
        if (isset($this->changedAttributes['status'])) {
            $changes[] = 'estado';
        }
        if (isset($this->changedAttributes['professional_id'])) {
            $changes[] = 'profesional';
        }

        $changeText = !empty($changes) ? ' (' . implode(', ', $changes) . ')' : '';

        $mail = (new MailMessage)
            ->subject("Cita actualizada - {$clinic->name}")
            ->greeting("Hola {$patient->first_name},")
            ->line("Tu cita en {$clinic->name} ha sido modificada{$changeText}.")
            ->line("Nueva fecha: {$this->appointment->start_time->format('d/m/Y')}")
            ->line("Nueva hora: {$this->appointment->start_time->format('H:i')}")
            ->line("Por favor, revisa los detalles y contacta con la clínica si tienes alguna duda.")
            ->salutation("Saludos, {$clinic->name}");

        $mail->viewData = ['appointment' => $this->appointment, 'clinic' => $clinic];

        return $mail;
    }
}
