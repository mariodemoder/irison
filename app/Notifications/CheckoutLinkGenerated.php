<?php

namespace App\Notifications;

use App\Models\SubscriptionRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CheckoutLinkGenerated extends Notification
{
    use Queueable;

    public function __construct(
        public readonly SubscriptionRequest $request,
    ) {}

    public function via($notifiable)
    {
        return ['database', 'mail'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'title' => 'Enlace de pago generado para upgrade',
            'message' => 'El upgrade de ' . $this->request->current_plan . ' a ' . $this->request->requested_plan . ' está listo para pago',
            'type' => 'checkout_link_generated',
            'request_id' => $this->request->id,
            'checkout_url' => $this->request->checkout_url,
        ];
    }
}