<?php

declare(strict_types=1);

namespace Modules\Notifications\Backoffice\Notifications;

use App\Models\SubscriptionRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Notification;

class SubscriptionUpgradeRequestedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly SubscriptionRequest $request,
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): DatabaseMessage
    {
        return new DatabaseMessage([
            'type' => 'upgrade_requested',
            'request_id' => $this->request->id,
            'plan' => $this->request->requested_plan,
            'clinic_name' => $this->request->clinic->name ?? '',
            'requester_name' => $this->request->requester?->name ?? '',
            'message' => "Se ha solicitado una actualización al plan {$this->request->requested_plan}.",
        ]);
    }
}
