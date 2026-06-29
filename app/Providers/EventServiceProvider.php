<?php

namespace App\Providers;

use App\Events\ConsentCreated;
use App\Events\ConsentRevoked;
use App\Events\ConsentSent;
use App\Events\ConsentSigned;
use App\Listeners\LogConsentActivity;
use App\Listeners\SendConsentEmail;
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
    ];

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
