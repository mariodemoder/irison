<?php

namespace App\Listeners;

use App\Events\PaymentCompleted;
use App\Mail\PaymentCompletedMail;
use App\Notifications\PaymentCompletedNotification;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendPaymentConfirmationEmail
{
    use InteractsWithQueue;

    public function handle(PaymentCompleted $event): void
    {
        try {
            $request = $event->request;
            $recipient = $request->clinic->ownerUser()->first()
                ?? $request->clinic->users()->orderBy('id')->first();

            if (! $recipient) {
                throw new \RuntimeException('No recipient user found for payment notification');
            }

            $recipient->notify(new PaymentCompletedNotification($request));

            if (filter_var((string) $recipient->email, FILTER_VALIDATE_EMAIL)) {
                Mail::to($recipient->email)->queue(new PaymentCompletedMail($request));
            }
        } catch (\Throwable $e) {
            Log::error('Failed to send payment confirmation email', [
                'request_id' => $event->request->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}