<?php

namespace App\Mail;

use App\Models\SubscriptionRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SubscriptionStatusMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly SubscriptionRequest $request,
    ) {}

    public function envelope(): Envelope
    {
        $statusText = $this->request->status === 'approved' ? 'aprobada' : 'rechazada';
        return new Envelope(
            subject: 'Tu solicitud de upgrade ha sido ' . $statusText,
        );
    }

    public function content(): Content
    {
        $statusText = $this->request->status === 'approved' ? 'aprobada' : 'rechazada';
        return new Content(
            view: 'emails.subscription-status',
            with: [
                'clinicName' => $this->request->clinic->name ?? '-',
                'currentPlan' => $this->request->current_plan,
                'requestedPlan' => $this->request->requested_plan,
                'status' => $statusText,
                'comments' => $this->request->reviewer_comments ?? '-',
            ],
        );
    }
}
