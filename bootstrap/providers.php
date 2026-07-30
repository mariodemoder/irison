<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\AuthServiceProvider::class,
    Modules\Booking\Providers\BookingServiceProvider::class,
    Modules\Bonus\Providers\BonusServiceProvider::class,
    Modules\Notifications\Infrastructure\Providers\NotificationsServiceProvider::class,
];
