<?php

namespace App\Listeners;

use App\Events\SubscriptionUpgraded;
use App\Notifications\SubscriptionUpgradedNotification;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class UpgradeSubscription
{
    use InteractsWithQueue;

    public function handle(SubscriptionUpgraded $event): void
    {
        try {
            $request = $event->request;
            $recipient = $request->clinic->ownerUser()->first()
                ?? $request->clinic->users()->orderBy('id')->first();

            if (! $recipient) {
                throw new \RuntimeException('No recipient user found for upgraded notification');
            }

            $recipient->notify(new SubscriptionUpgradedNotification($request));
        } catch (\Throwable $e) {
            Log::error('Failed to send subscription upgraded notification', [
                'request_id' => $event->request->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}