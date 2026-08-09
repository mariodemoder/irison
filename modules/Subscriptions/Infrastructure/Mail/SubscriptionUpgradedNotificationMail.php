<?php

namespace Modules\Subscriptions\Infrastructure\Mail;

use App\Models\SubscriptionRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SubscriptionUpgradedNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly SubscriptionRequest $request,
        public readonly ?string $invoiceUrl = null,
        public readonly ?string $receiptUrl = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Irison: Su upgrade de suscripción ha sido completado - ¡Bienvenido a su nuevo plan!',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails/subscription-upgraded-notification',
            with: [
                'clinicName' => $this->request->clinic->name ?? '-',
                'currentPlan' => $this->request->current_plan,
                'requestedPlan' => $this->request->requested_plan,
                'completedAt' => $this->request->completed_at,
                'reviewerComments' => $this->request->reviewer_comments ?? '-',
                'invoiceUrl' => $this->invoiceUrl,
                'receiptUrl' => $this->receiptUrl,
            ],
        );
    }
}