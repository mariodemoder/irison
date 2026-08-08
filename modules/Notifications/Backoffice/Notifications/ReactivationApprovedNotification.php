<?php

declare(strict_types=1);

namespace Modules\Notifications\Backoffice\Notifications;

use App\Models\SubscriptionRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReactivationApprovedNotification extends Notification implements ShouldQueue
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
            'type' => 'reactivation_approved',
            'request_id' => $this->request->id,
            'message' => 'Tu solicitud de reactivación de la cuenta ha sido aprobada. El equipo de Irison se pondrá en contacto contigo para completar el proceso.',
        ]);
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Tu solicitud de reactivación ha sido aprobada')
            ->view('emails.reactivation-status', [
                'clinicName' => $this->request->clinic->name ?? '',
                'status' => 'aprobada',
                'comments' => $this->request->reviewer_comments ?? '-',
            ]);
    }
}
