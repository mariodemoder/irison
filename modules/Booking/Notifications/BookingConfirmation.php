<?php

declare(strict_types=1);

namespace Modules\Booking\Notifications;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingConfirmation extends Notification
{
    use Queueable;

    public function __construct(
        private Appointment $appointment
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $cancelUrl = config('app.frontend_url') . '/booking/cancel/' . $this->appointment->confirmation_token;

        $mail = (new MailMessage)
            ->subject('Confirmación de cita — ' . ($this->appointment->clinic->name ?? 'Irison'))
            ->greeting('Hola ' . $this->appointment->patient->first_name . ',')
            ->line('Tu cita ha sido confirmada.')
            ->line('**Profesional:** ' . ($this->appointment->professional->name ?? 'Profesional por asignar'))
            ->line('**Fecha:** ' . $this->appointment->start_time->format('d/m/Y'))
            ->line('**Hora:** ' . $this->appointment->start_time->format('H:i') . ' - ' . $this->appointment->end_time->format('H:i'))
            ->line('**Clínica:** ' . ($this->appointment->clinic->name ?? '—'))
            ->action('Cancelar cita', $cancelUrl)
            ->line('Si no puedes asistir, cancela con al menos 24 horas de antelación.')
            ->salutation('Gracias por confiar en ' . ($this->appointment->clinic->name ?? 'Irison'));

        $mail->viewData = ['appointment' => $this->appointment, 'clinic' => $this->appointment->clinic];

        return $mail;
    }
}
