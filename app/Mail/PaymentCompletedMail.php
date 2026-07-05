<?php

namespace App\Mail;

use App\Models\SubscriptionRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentCompletedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly SubscriptionRequest $request,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Irison: Su upgrade de suscripción ha sido completado',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.payment-completed',
            with: [
                'clinicName' => $this->request->clinic->name ?? '-',
                'currentPlan' => $this->request->current_plan,
                'requestedPlan' => $this->request->requested_plan,
                'completedAt' => $this->request->completed_at,
            ],
        );
    }
}