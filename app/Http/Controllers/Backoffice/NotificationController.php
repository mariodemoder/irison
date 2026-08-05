<?php

declare(strict_types=1);

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    public function markRead(Request $request, DatabaseNotification $notification): RedirectResponse
    {
        $admin = $request->user('admin');

        if ($admin
            && (string) $notification->notifiable_id === (string) $admin->getKey()
            && $notification->notifiable_type === get_class($admin)
        ) {
            $notification->markAsRead();
        }

        return back();
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $admin = $request->user('admin');

        if ($admin) {
            $admin->unreadNotifications->markAsRead();
        }

        return back();
    }
}
