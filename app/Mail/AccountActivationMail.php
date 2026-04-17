<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AccountActivationMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public User $user,
        public string $activationUrl,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Activa tu cuenta en Irison',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.account-activation',
            with: [
                'name' => $this->user->name,
                'activationUrl' => $this->activationUrl,
            ],
        );
    }
}
