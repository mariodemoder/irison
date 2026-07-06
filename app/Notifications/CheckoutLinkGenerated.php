<?php

namespace App\Notifications;

use App\Models\SubscriptionRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
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

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Irison: Tu enlace de pago para upgrade está listo')
            ->greeting('Hola ' . ($notifiable->name ?? ''))
            ->line('Tu solicitud de upgrade de ' . $this->request->current_plan . ' a ' . $this->request->requested_plan . ' ha sido aprobada.')
            ->line('Para completar el cambio de plan, finaliza el pago desde el siguiente enlace:')
            ->action('Completar pago', (string) $this->request->checkout_url)
            ->line('Si el botón no funciona, copia y pega esta URL en tu navegador:')
            ->line((string) $this->request->checkout_url);
    }
}