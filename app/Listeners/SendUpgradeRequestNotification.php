<?php

namespace App\Listeners;

use App\Events\UpgradeRequested;
use App\Mail\SubscriptionRequestMail;
use App\Notifications\SubscriptionUpgradeRequested;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendUpgradeRequestNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(UpgradeRequested $event): void
    {
        $request = $event->request;

        $requesterEmail = (string) ($request->requester?->email ?? '');
        if (filter_var($requesterEmail, FILTER_VALIDATE_EMAIL)) {
            try {
                Mail::to($requesterEmail)->queue(new SubscriptionRequestMail($request));
            } catch (\Throwable $e) {
                Log::error('Failed to send subscription request mail to requester', [
                    'request_id' => $request->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        try {
            $admins = $request->clinic->getAdmins();

            foreach ($admins as $admin) {
                $admin->notify(new SubscriptionUpgradeRequested($request));
            }
        } catch (\Throwable $e) {
            Log::error('Failed to send upgrade request notification', [
                'request_id' => $event->request->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
