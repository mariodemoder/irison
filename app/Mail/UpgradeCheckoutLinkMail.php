<?php

namespace App\Mail;

use App\Models\SubscriptionRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UpgradeCheckoutLinkMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly SubscriptionRequest $request,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Irison: Su solicitud de upgrade ha sido aprobada - Complete su pago',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.upgrade-checkout-link',
            with: [
                'clinicName' => $this->request->clinic->name ?? '-',
                'clinicId' => $this->request->clinic_id,
                'clinicEmail' => $this->request->clinic->email ?? '-',
                'currentPlan' => $this->request->current_plan,
                'requestedPlan' => $this->request->requested_plan,
                'checkoutUrl' => $this->request->checkout_url,
                'sessionId' => $this->request->stripe_checkout_session_id,
            ],
        );
    }
}