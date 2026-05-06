<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AppointmentReminderMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Appointment $appointment,
        public int $hoursBefore,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Recordatorio de cita en ' . $this->hoursBefore . 'h',
        );
    }

    public function content(): Content
    {
        $timezone = $this->appointment->clinic?->timezone ?: config('app.timezone');
        $start = $this->appointment->start_time?->copy()->timezone($timezone);

        return new Content(
            view: 'emails.appointment-reminder',
            with: [
                'hoursBefore' => $this->hoursBefore,
                'patientName' => $this->appointment->patient?->name ?: 'Paciente',
                'clinicName' => $this->appointment->clinic?->name ?: 'Clínica',
                'dateText' => $start?->format('d/m/Y') ?: '-',
                'timeText' => $start?->format('H:i') ?: '-',
            ],
        );
    }
}
