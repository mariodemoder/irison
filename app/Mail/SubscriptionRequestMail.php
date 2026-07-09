<?php

namespace App\Mail;

use App\Models\SubscriptionRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SubscriptionRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly SubscriptionRequest $request,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Irison: solicitud de upgrade a ' . strtoupper($this->request->requested_plan),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.subscription-request-internal',
            with: [
                'clinicName' => $this->request->clinic->name ?? '-',
                'clinicId' => $this->request->clinic_id,
                'clinicEmail' => $this->request->clinic->email ?? '-',
                'currentPlan' => $this->request->current_plan,
                'requestedPlan' => $this->request->requested_plan,
                'comments' => $this->request->comments ?? '-',
                'requestedAt' => $this->request->created_at->format('d/m/Y H:i'),
            ],
        );
    }
}
