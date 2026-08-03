<?php

declare(strict_types=1);

namespace Modules\Notifications\Patient\Notifications;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AppointmentCancelledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Appointment $appointment,
    ) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $clinic = $this->appointment->clinic;
        $patient = $this->appointment->patient;

        $mail = (new MailMessage)
            ->subject("Cita cancelada - {$clinic->name}")
            ->greeting("Hola {$patient->first_name},")
            ->line("Tu cita en {$clinic->name} del {$this->appointment->start_time->format('d/m/Y')} a las {$this->appointment->start_time->format('H:i')} ha sido cancelada.")
            ->line("Si deseas reagendar, por favor contacta con la clínica.")
            ->salutation("Saludos, {$clinic->name}");

        $mail->viewData = ['appointment' => $this->appointment];

        return $mail;
    }
}
