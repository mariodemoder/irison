<?php

namespace App\Listeners;

use App\Events\PaymentCompleted;
use App\Notifications\PaymentCompletedNotification;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendPaymentConfirmationEmail
{
    use InteractsWithQueue;

    public function handle(PaymentCompleted $event): void
    {
        try {
            $request = $event->request;

            $request->clinic->user->notify(new PaymentCompletedNotification($request));
        } catch (\Throwable $e) {
            Log::error('Failed to send payment confirmation email', [
                'request_id' => $event->request->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}