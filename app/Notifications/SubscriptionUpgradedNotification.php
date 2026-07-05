<?php

namespace App\Notifications;

use App\Models\SubscriptionRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SubscriptionUpgradedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly SubscriptionRequest $request,
    ) {}

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'title' => 'Suscripción actualizada exitosamente',
            'message' => 'Su suscripción ha sido actualizada a: ' . $this->request->requested_plan,
            'type' => 'subscription_upgraded',
            'request_id' => $this->request->id,
        ];
    }
}