<?php

declare(strict_types=1);

namespace App\Http\View\Composers;

use Illuminate\View\View;

class BackofficeNotificationsComposer
{
    public function compose(View $view): void
    {
        $admin = auth('admin')->user();

        $view->with(
            'adminUnreadNotifications',
            $admin ? $admin->unreadNotifications()->latest()->limit(10)->get() : collect(),
        );

        $view->with(
            'adminUnreadCount',
            $admin ? (int) $admin->unreadNotifications()->count() : 0,
        );
    }
}
