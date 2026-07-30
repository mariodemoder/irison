<?php

declare(strict_types=1);

namespace Modules\Notifications\Backoffice\Listeners;

use App\Events\SubscriptionUpgraded;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Notifications\Backoffice\Notifications\SubscriptionUpgradedNotification;

class UpgradeSubscription implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
