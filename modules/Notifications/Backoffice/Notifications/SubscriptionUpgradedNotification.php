<?php

declare(strict_types=1);

namespace Modules\Notifications\Backoffice\Notifications;

use App\Models\SubscriptionRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionUpgradedNotification extends Notification implements ShouldQueue
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
            'type' => 'subscription_upgraded',
            'request_id' => $this->request->id,
            'plan' => $this->request->requested_plan,
            'message' => "La suscripción se ha actualizado al plan {$this->request->requested_plan}.",
        ]);
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Suscripción actualizada')
            ->view('emails.subscription-upgraded-notification', [
                'clinicName' => $this->request->clinic->name ?? '',
                'currentPlan' => ucfirst($this->request->current_plan),
                'requestedPlan' => ucfirst($this->request->requested_plan),
                'completedAt' => $this->request->completed_at,
                'reviewerComments' => $this->request->reviewer_comments ?? '',
            ]);
    }
}
