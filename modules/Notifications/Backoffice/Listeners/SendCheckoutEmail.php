<?php

declare(strict_types=1);

namespace Modules\Notifications\Backoffice\Listeners;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Notifications\Backoffice\Notifications\CheckoutLinkGeneratedNotification;
use Modules\Subscriptions\Domain\Events\CheckoutCreated;

class SendCheckoutEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(CheckoutCreated $event): void
    {
        try {
            $request = $event->request;
            $recipient = $request->clinic->ownerUser()->first()
                ?? $request->clinic->users()->orderBy('id')->first();

            if (! $recipient) {
                throw new \RuntimeException('No recipient user found for clinic checkout notification');
            }

            $recipient->notify(new CheckoutLinkGeneratedNotification($request));
        } catch (\Throwable $e) {
            Log::error('Failed to send checkout email', [
                'request_id' => $event->request->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
