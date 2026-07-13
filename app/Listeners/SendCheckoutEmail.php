<?php

namespace App\Listeners;

use App\Events\CheckoutCreated;
use App\Mail\UpgradeCheckoutLinkMail;
use App\Notifications\CheckoutLinkGenerated;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

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

            $recipient->notify(new CheckoutLinkGenerated($request));

            if (filter_var((string) $recipient->email, FILTER_VALIDATE_EMAIL)) {
                Mail::to($recipient->email)->queue(new UpgradeCheckoutLinkMail($request));
            }
        } catch (\Throwable $e) {
            Log::error('Failed to send checkout email', [
                'request_id' => $event->request->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
