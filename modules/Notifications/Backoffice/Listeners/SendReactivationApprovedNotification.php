<?php

declare(strict_types=1);

namespace Modules\Notifications\Backoffice\Listeners;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Notifications\Backoffice\Notifications\ReactivationApprovedNotification;
use Modules\Subscriptions\Domain\Events\ReactivationApproved;

class SendReactivationApprovedNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(ReactivationApproved $event): void
    {
        try {
            $request = $event->request;
            $recipient = $request->clinic->ownerUser()->first()
                ?? $request->clinic->users()->orderBy('id')->first();

            if (! $recipient) {
                throw new \RuntimeException('No recipient user found for reactivation approved notification');
            }

            $recipient->notify(new ReactivationApprovedNotification($request));
        } catch (\Throwable $e) {
            Log::error('Failed to send reactivation approved notification', [
                'request_id' => $event->request->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
