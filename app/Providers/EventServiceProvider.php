<?php

namespace App\Providers;

use App\Events\ConsentCreated;
use App\Events\ConsentRevoked;
use App\Events\ConsentSent;
use App\Events\ConsentSigned;
use App\Events\UpgradeRequested;
use App\Events\CheckoutCreated;
use App\Events\PaymentCompleted;
use App\Events\SubscriptionUpgraded;
use App\Listeners\LogConsentActivity;
use App\Listeners\SendConsentEmail;
use App\Listeners\SendCheckoutEmail;
use App\Listeners\SendPaymentConfirmationEmail;
use App\Listeners\SendUpgradeRequestNotification;
use App\Listeners\UpgradeSubscription;
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

        UpgradeRequested::class => [
            [SendUpgradeRequestNotification::class, 'handle'],
        ],
        CheckoutCreated::class => [
            [SendCheckoutEmail::class, 'handle'],
        ],
        PaymentCompleted::class => [
            [SendPaymentConfirmationEmail::class, 'handle'],
            [UpgradeSubscription::class, 'handle'],
        ],
        SubscriptionUpgraded::class => [
            [UpgradeSubscription::class, 'handle'],
        ],
    ];

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}