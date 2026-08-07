<?php

declare(strict_types=1);

namespace Modules\Notifications\Backoffice\Listeners;

use Illuminate\Bus\Queueable;
use Modules\Subscriptions\Domain\Events\SubscriptionRejected;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Notifications\Backoffice\Notifications\SubscriptionRejectedNotification;

class SendSubscriptionRejectedNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(SubscriptionRejected $event): void
    {
        try {
            $request = $event->request;
            $recipient = $request->clinic->ownerUser()->first()
                ?? $request->clinic->users()->orderBy('id')->first();

            if (! $recipient) {
                throw new \RuntimeException('No recipient user found for rejected notification');
            }

            $recipient->notify(new SubscriptionRejectedNotification($request));
        } catch (\Throwable $e) {
            Log::error('Failed to send subscription rejected notification', [
                'request_id' => $event->request->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
