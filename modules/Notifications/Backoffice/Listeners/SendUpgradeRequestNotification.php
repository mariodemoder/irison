<?php

declare(strict_types=1);

namespace Modules\Notifications\Backoffice\Listeners;

use Illuminate\Bus\Queueable;
use Modules\Subscriptions\Domain\Events\UpgradeRequested;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Notifications\Backoffice\Notifications\SubscriptionUpgradeRequestedNotification;

class SendUpgradeRequestNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(UpgradeRequested $event): void
    {
        $request = $event->request;

        try {
            $admins = $request->clinic->getAdmins();

            foreach ($admins as $admin) {
                $admin->notify(new SubscriptionUpgradeRequestedNotification($request));
            }
        } catch (\Throwable $e) {
            Log::error('Failed to send upgrade request notification', [
                'request_id' => $event->request->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
