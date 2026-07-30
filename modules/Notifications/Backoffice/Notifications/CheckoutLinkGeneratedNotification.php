<?php

declare(strict_types=1);

namespace Modules\Notifications\Backoffice\Notifications;

use App\Models\SubscriptionRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CheckoutLinkGeneratedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly SubscriptionRequest $request,
    ) {}

    public function via($notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toDatabase($notifiable): DatabaseMessage
    {
        return new DatabaseMessage([
            'type' => 'checkout_link_generated',
            'request_id' => $this->request->id,
            'checkout_url' => $this->request->checkout_url ?? '',
            'message' => 'El enlace de pago para la actualización está listo.',
        ]);
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Enlace de pago para actualización de plan')
            ->view('emails.upgrade-checkout-link', [
                'checkoutUrl' => $this->request->checkout_url,
                'clinicName' => $this->request->clinic->name ?? '',
                'currentPlan' => ucfirst($this->request->current_plan),
                'requestedPlan' => ucfirst($this->request->requested_plan),
                'clinicId' => $this->request->clinic->id ?? '',
                'clinicEmail' => $this->request->clinic->email ?? '',
                'sessionId' => $this->request->stripe_checkout_session_id ?? '',
            ]);
    }
}
