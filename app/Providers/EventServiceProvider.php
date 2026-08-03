<?php

namespace App\Providers;

use App\Events\AppointmentCancelled;
use App\Events\AppointmentCreated;
use App\Events\AppointmentUpdated;
use App\Events\ConsentCreated;
use App\Events\ConsentRevoked;
use App\Events\ConsentSent;
use App\Events\ConsentSigned;
use App\Events\UpgradeRequested;
use App\Events\CheckoutCreated;
use App\Events\PaymentCompleted;
use App\Events\SubscriptionUpgraded;
use App\Listeners\LogConsentActivity;
use Illuminate\Mail\Events\MessageSent;
use Modules\Notifications\Infrastructure\Listeners\LogSentMail;
use Modules\Notifications\Patient\Listeners\SendConsentEmail;
use Modules\Notifications\Patient\Listeners\SendAppointmentStatusNotification;
use Modules\Notifications\Backoffice\Listeners\SendCheckoutEmail;
use Modules\Notifications\Backoffice\Listeners\SendPaymentConfirmationEmail;
use Modules\Notifications\Backoffice\Listeners\SendUpgradeRequestNotification;
use Modules\Notifications\Backoffice\Listeners\UpgradeSubscription;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        ConsentCreated::class => [
            [LogConsentActivity::class, 'handleCreated'],
        ],
        ConsentSent::class => [
            [LogConsentActivity::class, 'handleSent'],
            [SendConsentEmail::class, 'handle'],
        ],
        ConsentSigned::class => [
            [LogConsentActivity::class, 'handleSigned'],
        ],
        ConsentRevoked::class => [
            [LogConsentActivity::class, 'handleRevoked'],
        ],

        AppointmentCreated::class => [
            [SendAppointmentStatusNotification::class, 'handleAppointmentCreated'],
        ],
        AppointmentUpdated::class => [
            [SendAppointmentStatusNotification::class, 'handleAppointmentUpdated'],
        ],
        AppointmentCancelled::class => [
            [SendAppointmentStatusNotification::class, 'handleAppointmentCancelled'],
        ],

        UpgradeRequested::class => [
            [SendUpgradeRequestNotification::class, 'handle'],
        ],
        CheckoutCreated::class => [
            [SendCheckoutEmail::class, 'handle'],
        ],
        PaymentCompleted::class => [
            [SendPaymentConfirmationEmail::class, 'handle'],
        ],
        SubscriptionUpgraded::class => [
            [UpgradeSubscription::class, 'handle'],
        ],

        MessageSent::class => [
            LogSentMail::class,
        ],
    ];

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}