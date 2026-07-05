<?php

namespace App\Listeners;

use App\Events\CheckoutCreated;
use App\Notifications\CheckoutLinkGenerated;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendCheckoutEmail
{
    use InteractsWithQueue;

    public function handle(CheckoutCreated $event): void
    {
        try {
            $request = $event->request;

            $request->clinic->user->notify(new CheckoutLinkGenerated($request));
        } catch (\Throwable $e) {
            Log::error('Failed to send checkout email', [
                'request_id' => $event->request->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}