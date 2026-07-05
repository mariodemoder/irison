<?php

namespace App\Listeners;

use App\Events\UpgradeRequested;
use App\Notifications\SubscriptionUpgradeRequested;
use Illuminate\Notifications\Notifiable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendUpgradeRequestNotification
{
    use InteractsWithQueue;

    public function handle(UpgradeRequested $event): void
    {
        try {
            $request = $event->request;
            $admins = $request->clinic->getAdmins();

            foreach ($admins as $admin) {
                $admin->notify(new SubscriptionUpgradeRequested($request));
            }
        } catch (\Throwable $e) {
            Log::error('Failed to send upgrade request notification', [
                'request_id' => $event->request->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}