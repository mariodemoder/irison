<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

/**
 * Restablecimiento de contraseña para PACIENTES (Portal del Paciente).
 *
 * Extiende la notificación compartida pero:
 *  - Construye la URL hacia /patient/reset-password (SPA del paciente), nunca
 *    hacia la ruta de staff /reset-password.
 *  - Añade ?clinic={slug} para que la página pública pueda mostrar el branding
 *    de la clínica antes de autenticar, y ?email={email real}&name={nombre apellido}
 *    para pre-rellenar el formulario y saludar al paciente. El token interno sigue
 *    keyed por patient id (getEmailForPasswordReset()).
 *  - Personaliza el saludo del cuerpo con el nombre/apellido del paciente.
 *  - Usa el nombre de la clínica como remitente (From name) y propaga $clinic
 *    al header/footer del email (sin marca Irison); el nombre de la clínica se
 *    muestra como título centrado en el header.
 *
 * El staff (App\Models\User) sigue usando ResetPasswordNotificationEs y su
 * ruta /reset-password original.
 */
class PatientResetPasswordNotification extends ResetPasswordNotificationEs
{
    public function toMail($notifiable): MailMessage
    {
        $mail = parent::toMail($notifiable);

        // Saludo personalizado con el nombre/apellido del paciente.
        $mail->greeting('Hola, ' . trim($notifiable->first_name . ' ' . $notifiable->last_name) . ',');

        $clinic = $notifiable->clinic;

        if ($clinic) {
            $mail->from(config('mail.from.address'), $clinic->name);
            $mail->viewData = array_merge($mail->viewData ?? [], ['clinic' => $clinic]);
        }

        return $mail;
    }

    protected function resetUrl($notifiable): string
    {
        $base = rtrim((string) config('app.frontend_url', config('app.url')), '/');

        $query = http_build_query([
            'token' => $this->token,
            // El email real (no el id) para pre-rellenar el formulario; el token
            // interno sigue keyed por patient id (ver getEmailForPasswordReset()).
            'email' => $notifiable->email,
            'name' => trim($notifiable->first_name . ' ' . $notifiable->last_name),
            'clinic' => $notifiable->clinic?->slug ?? '',
        ]);

        return $base . '/patient/reset-password?' . $query;
    }
}