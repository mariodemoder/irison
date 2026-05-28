<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Clinic;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TrialLifecycleMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Clinic $clinic,
        public string $milestone,
        public string $subjectLine,
        public string $headline,
        public string $message,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.trial-lifecycle',
            with: [
                'clinicName' => (string) $this->clinic->name,
                'milestone' => $this->milestone,
                'headline' => $this->headline,
                'messageBody' => $this->message,
                'trialEndsAt' => $this->clinic->trial_ends_at,
                'billingUrl' => rtrim((string) config('app.frontend_url', config('app.url')), '/') . '/billing',
            ],
        );
    }
}
