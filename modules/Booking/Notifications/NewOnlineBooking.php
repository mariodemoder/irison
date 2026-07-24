<?php

declare(strict_types=1);

namespace Modules\Booking\Notifications;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewOnlineBooking extends Notification
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
        return (new MailMessage)
            ->subject('Nueva reserva online — ' . $this->appointment->patient->full_name ?? 'Paciente')
            ->greeting('Nueva reserva online')
            ->line('Se ha realizado una nueva reserva a través de la página de reserva online.')
            ->line('**Paciente:** ' . ($this->appointment->patient->first_name ?? '') . ' ' . ($this->appointment->patient->last_name ?? ''))
            ->line('**Email:** ' . ($this->appointment->patient->email ?? '—'))
            ->line('**Teléfono:** ' . ($this->appointment->patient->phone ?? '—'))
            ->line('**Fecha:** ' . $this->appointment->start_time->format('d/m/Y'))
            ->line('**Hora:** ' . $this->appointment->start_time->format('H:i') . ' - ' . $this->appointment->end_time->format('H:i'))
            ->line('**Notas:** ' . ($this->appointment->booking_notes ?? '—'))
            ->salutation('— Irison');
    }
}
