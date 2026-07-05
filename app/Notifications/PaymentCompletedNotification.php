<?php

namespace App\Notifications;

use App\Models\SubscriptionRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PaymentCompletedNotification extends Notification
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
            'title' => 'Pago completado - Upgrade procesado',
            'message' => 'El pago para el upgrade de ' . $this->request->current_plan . ' a ' . $this->request->requested_plan . ' ha sido completado',
            'type' => 'payment_completed',
            'request_id' => $this->request->id,
        ];
    }
}