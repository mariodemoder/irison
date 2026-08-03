<?php

declare(strict_types=1);

namespace Modules\Notifications\Domain\Enums;

enum EmailCategory: string
{
    case Reminder24h = 'reminder_24h';
    case Reminder2h = 'reminder_2h';
    case AppointmentCreated = 'appointment_created';
    case AppointmentUpdated = 'appointment_updated';
    case AppointmentCancelled = 'appointment_cancelled';
    case BookingConfirmation = 'booking_confirmation';
    case NewOnlineBooking = 'new_online_booking';
    case ConsentSignRequest = 'consent_sign_request';
    case SubscriptionActivated = 'subscription_activated';
    case CheckoutLink = 'checkout_link';
    case PaymentCompleted = 'payment_completed';
    case SubscriptionUpgraded = 'subscription_upgraded';
    case InvoicePaymentFailed = 'invoice_payment_failed';
    case InvoiceResend = 'invoice_resend';
    case SubscriptionCanceledInternal = 'subscription_canceled_internal';
    case Contact = 'contact';
    case AccountActivation = 'account_activation';
    case TrialLifecycle = 'trial_lifecycle';
    case PasswordReset = 'password_reset';
    case Generic = 'generic';

    public function label(): string
    {
        return match ($this) {
            self::Reminder24h => 'Recordatorio 24h',
            self::Reminder2h => 'Recordatorio 2h',
            self::AppointmentCreated => 'Nueva cita',
            self::AppointmentUpdated => 'Cita modificada',
            self::AppointmentCancelled => 'Cita cancelada',
            self::BookingConfirmation => 'Reserva online',
            self::NewOnlineBooking => 'Nueva reserva online',
            self::ConsentSignRequest => 'Firma de consentimiento',
            self::SubscriptionActivated => 'Suscripción activada',
            self::CheckoutLink => 'Enlace de pago',
            self::PaymentCompleted => 'Pago completado',
            self::SubscriptionUpgraded => 'Suscripción actualizada',
            self::InvoicePaymentFailed => 'Pago de factura fallido',
            self::InvoiceResend => 'Reenvío de factura',
            self::SubscriptionCanceledInternal => 'Suscripción cancelada (interno)',
            self::Contact => 'Contacto',
            self::AccountActivation => 'Activación de cuenta',
            self::TrialLifecycle => 'Hito de trial',
            self::PasswordReset => 'Restablecer contraseña',
            self::Generic => 'Genérico',
        };
    }

    public static function labelFor(string $category): string
    {
        return self::tryFrom($category)?->label() ?? $category;
    }

    public function isReminder(): bool
    {
        return $this === self::Reminder24h || $this === self::Reminder2h;
    }
}
