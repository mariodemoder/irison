<?php

declare(strict_types=1);

namespace Modules\Notifications\Backoffice\Notifications;

use App\Models\SubscriptionRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionRejectedNotification extends Notification implements ShouldQueue
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
        if ($this->request->isReactivation()) {
            return new DatabaseMessage([
                'type' => 'subscription_rejected',
                'request_id' => $this->request->id,
                'message' => 'Tu solicitud de reactivación de la cuenta ha sido rechazada.',
            ]);
        }

        return new DatabaseMessage([
            'type' => 'subscription_rejected',
            'request_id' => $this->request->id,
            'plan' => $this->request->requested_plan,
            'message' => "La solicitud de upgrade al plan {$this->request->requested_plan} ha sido rechazada.",
        ]);
    }

    public function toMail($notifiable): MailMessage
    {
        if ($this->request->isReactivation()) {
            return (new MailMessage)
                ->subject('Tu solicitud de reactivación ha sido rechazada')
                ->view('emails.reactivation-status', [
                    'clinicName' => $this->request->clinic->name ?? '',
                    'status' => 'rechazada',
                    'comments' => $this->request->reviewer_comments ?? '-',
                ]);
        }

        return (new MailMessage)
            ->subject('Tu solicitud de upgrade ha sido rechazada')
            ->view('emails.subscription-status', [
                'clinicName' => $this->request->clinic->name ?? '',
                'currentPlan' => $this->request->current_plan,
                'requestedPlan' => $this->request->requested_plan,
                'status' => 'rechazada',
                'comments' => $this->request->reviewer_comments ?? '-',
                'invoiceUrl' => null,
            ]);
    }
}
