<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public int $clinicId,
        public string $clinicName,
        public string $senderName,
        public string $senderEmail,
        public string $contactSubject,
        public string $body,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[Contacto] ' . $this->contactSubject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.contact',
            with: [
                'clinicId'       => $this->clinicId,
                'clinicName'     => $this->clinicName,
                'senderName'     => $this->senderName,
                'senderEmail'    => $this->senderEmail,
                'subject'        => $this->contactSubject,
                'body'           => $this->body,
            ],
        );
    }
}
