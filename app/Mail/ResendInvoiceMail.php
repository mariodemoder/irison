<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ResendInvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $clinicName,
        public readonly string $invoiceUrl,
        public readonly string $subject,
        public readonly string $message,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.resend-invoice',
            with: [
                'clinicName' => $this->clinicName,
                'invoiceUrl' => $this->invoiceUrl,
                'message' => $this->message,
            ],
        );
    }
}
