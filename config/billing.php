<?php

return [
    // provider: fake, stripe, redsys, etc.
    'provider' => env('BILLING_PROVIDER', 'fake'),

    // Optional alert to clinic when Stripe invoice payment fails.
    'notify_on_invoice_payment_failed' => env('BILLING_NOTIFY_ON_INVOICE_PAYMENT_FAILED', false),

    // Internal mailbox for subscription cancellation alerts.
    'cancellation_notification_to' => env('BILLING_CANCELLATION_NOTIFICATION_TO', env('MAIL_TEST_INBOX', '')),
];
