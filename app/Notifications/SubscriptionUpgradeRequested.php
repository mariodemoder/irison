<?php

namespace App\Notifications;

use App\Models\SubscriptionRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SubscriptionUpgradeRequested extends Notification
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
            'title' => 'Nuevo request de upgrade de suscripción',
            'message' => 'El ' . $this->request->clinic->name . ' solicita upgrade de ' . $this->request->current_plan . ' a ' . $this->request->requested_plan,
            'type' => 'subscription_upgrade_requested',
            'request_id' => $this->request->id,
        ];
    }
}