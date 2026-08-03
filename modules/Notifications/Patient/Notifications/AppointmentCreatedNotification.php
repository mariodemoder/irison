<?php

declare(strict_types=1);

namespace Modules\Notifications\Patient\Notifications;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AppointmentCreatedNotification extends Notification implements ShouldQueue
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
            ->subject("Nueva cita - {$clinic->name}")
            ->greeting("Hola {$patient->first_name},")
            ->line("Se ha creado una nueva cita en {$clinic->name}.")
            ->line("Fecha: {$this->appointment->start_time->format('d/m/Y')}")
            ->line("Hora: {$this->appointment->start_time->format('H:i')}")
            ->line("Por favor, confirma tu asistencia o contacta con la clínica si necesitas cambiar la cita.")
            ->salutation("Saludos, {$clinic->name}");

        $mail->viewData = ['appointment' => $this->appointment];

        return $mail;
    }
}
