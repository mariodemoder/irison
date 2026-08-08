<?php

declare(strict_types=1);

return [
    /*
    | Mapa de clases de email (Mailable / Notification) a categoría del log.
    | Las categorías con tipo dinámico (p. ej. recordatorios) se resuelven
    | en el listener a partir de los datos del mensaje.
    */
    'categories' => [
        App\Mail\ConsentSignRequestMail::class => 'consent_sign_request',
        App\Mail\AppointmentReminderMail::class => 'appointment_reminder',
        Modules\Subscriptions\Infrastructure\Mail\SubscriptionActivatedMail::class => 'subscription_activated',
        Modules\Subscriptions\Infrastructure\Mail\SubscriptionUpgradedNotificationMail::class => 'subscription_upgraded',
        Modules\Subscriptions\Infrastructure\Mail\SubscriptionCanceledInternalMail::class => 'subscription_canceled_internal',
        App\Mail\ResendInvoiceMail::class => 'invoice_resend',
        Modules\Subscriptions\Infrastructure\Mail\InvoicePaymentFailedMail::class => 'invoice_payment_failed',
        App\Mail\ContactMail::class => 'contact',
        App\Mail\AccountActivationMail::class => 'account_activation',
        App\Mail\TrialLifecycleMail::class => 'trial_lifecycle',

        Modules\Notifications\Patient\Notifications\AppointmentCreatedNotification::class => 'appointment_created',
        Modules\Notifications\Patient\Notifications\AppointmentUpdatedNotification::class => 'appointment_updated',
        Modules\Notifications\Patient\Notifications\AppointmentCancelledNotification::class => 'appointment_cancelled',
        Modules\Notifications\Patient\Notifications\AppointmentReminderNotification::class => 'appointment_reminder',

        Modules\Booking\Notifications\BookingConfirmation::class => 'booking_confirmation',
        Modules\Booking\Notifications\NewOnlineBooking::class => 'new_online_booking',

        Modules\Notifications\Backoffice\Notifications\SubscriptionUpgradedNotification::class => 'subscription_upgraded',
        Modules\Notifications\Backoffice\Notifications\SubscriptionRejectedNotification::class => 'subscription_rejected',
        Modules\Notifications\Backoffice\Notifications\ReactivationApprovedNotification::class => 'reactivation_approved',
        Modules\Notifications\Backoffice\Notifications\PaymentCompletedNotification::class => 'payment_completed',
        Modules\Notifications\Backoffice\Notifications\CheckoutLinkGeneratedNotification::class => 'checkout_link',

        App\Notifications\ResetPasswordNotificationEs::class => 'password_reset',
    ],
];
