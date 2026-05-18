<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SubscriptionCanceledInternalMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public string $clinicName,
        public int $clinicId,
        public string $clinicEmail,
        public string $stripeCustomerId,
        public string $stripeSubscriptionId,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Irison: suscripcion cancelada',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.subscription-canceled-internal',
            with: [
                'clinicName' => $this->clinicName,
                'clinicId' => $this->clinicId,
                'clinicEmail' => $this->clinicEmail,
                'stripeCustomerId' => $this->stripeCustomerId,
                'stripeSubscriptionId' => $this->stripeSubscriptionId,
            ],
        );
    }
}