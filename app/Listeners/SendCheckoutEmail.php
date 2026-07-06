<?php

namespace App\Listeners;

use App\Events\CheckoutCreated;
use App\Mail\UpgradeCheckoutLinkMail;
use App\Notifications\CheckoutLinkGenerated;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendCheckoutEmail
{
    use InteractsWithQueue;

    public function handle(CheckoutCreated $event): void
    {
        try {
            $request = $event->request;
            $recipient = $request->clinic->ownerUser()->first()
                ?? $request->clinic->users()->orderBy('id')->first();

            if (! $recipient) {
                throw new \RuntimeException('No recipient user found for clinic checkout notification');
            }

            // Notificacion interna en la app
            $recipient->notify(new CheckoutLinkGenerated($request));

            // Email visible en Mailpit/Mailhog con plantilla HTML de upgrade
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