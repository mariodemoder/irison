<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\AuthServiceProvider::class,
    Modules\Booking\Providers\BookingServiceProvider::class,
    Modules\Bonus\Providers\BonusServiceProvider::class,
    Modules\Notifications\Infrastructure\Providers\NotificationsServiceProvider::class,
    Modules\Finance\Infrastructure\Providers\FinanceServiceProvider::class,
    Modules\Activity\Infrastructure\Providers\ActivityServiceProvider::class,
];
