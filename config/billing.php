<?php

return [
    // provider: fake, stripe, redsys, etc.
    'provider' => env('BILLING_PROVIDER', 'fake'),

    // Trial grace window after trial ends while clinic stays read-only.
    'trial_grace_days' => (int) env('BILLING_TRIAL_GRACE_DAYS', 7),

    // Read-only window after paid period ends on canceled subscriptions.
    'cancellation_read_only_days' => (int) env('BILLING_CANCELLATION_READ_ONLY_DAYS', 7),

    // Optional alert to clinic when Stripe invoice payment fails.
    'notify_on_invoice_payment_failed' => env('BILLING_NOTIFY_ON_INVOICE_PAYMENT_FAILED', false),

    // Internal mailbox for subscription cancellation alerts.
    'cancellation_notification_to' => env('BILLING_CANCELLATION_NOTIFICATION_TO', env('MAIL_TEST_INBOX', '')),
];
