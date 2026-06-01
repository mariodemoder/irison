<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotificationEs extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        if (static::$toMailCallback) {
            return call_user_func(static::$toMailCallback, $notifiable, $this->token);
        }

        $expire = config('auth.passwords.' . config('auth.defaults.passwords') . '.expire');

        return (new MailMessage)
            ->subject('Restablecer contrasena')
            ->greeting('Hola,')
            ->line('Recibimos una solicitud para restablecer la contrasena de tu cuenta.')
            ->action('Restablecer contrasena', $this->resetUrl($notifiable))
            ->line("Este enlace caduca en {$expire} minutos.")
            ->line('Si no solicitaste este cambio, puedes ignorar este correo.');
    }
}
