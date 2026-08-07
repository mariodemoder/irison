<?php

declare(strict_types=1);

namespace Modules\Subscriptions\Infrastructure\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SubscriptionActivatedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public string $clinicName,
        public string $plan,
        public string $activatedAt,
        public ?string $invoiceUrl = null,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '¡Bienvenido a Irison! Tu plan ' . ucfirst($this->plan) . ' está activo',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.subscription-activated',
            with: [
                'clinicName' => $this->clinicName,
                'plan' => $this->plan,
                'activatedAt' => $this->activatedAt,
                'invoiceUrl' => $this->invoiceUrl,
            ],
        );
    }
}
