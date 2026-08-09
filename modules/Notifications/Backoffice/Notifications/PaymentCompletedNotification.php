<?php

declare(strict_types=1);

namespace Modules\Notifications\Backoffice\Notifications;

use App\Models\SubscriptionRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentCompletedNotification extends Notification implements ShouldQueue
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
            'type' => 'payment_completed',
            'request_id' => $this->request->id,
            'amount' => $this->request->amount ?? 0,
            'message' => 'El pago para la actualización se ha completado.',
        ]);
    }

    public function toMail($notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Pago completado - Actualización de plan')
            ->view('emails.payment-completed', [
                'clinicName' => $this->request->clinic->name ?? '',
                'currentPlan' => ucfirst($this->request->current_plan),
                'requestedPlan' => ucfirst($this->request->requested_plan),
                'completedAt' => $this->request->completed_at,
                'invoiceUrl' => $this->request->invoice_url,
                'receiptUrl' => $this->request->receipt_url,
            ]);

        $mail->viewData = array_merge($mail->viewData, ['request' => $this->request]);

        return $mail;
    }
}
