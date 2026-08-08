<?php

declare(strict_types=1);

namespace Modules\Notifications\Backoffice\Listeners;

use App\Services\Backoffice\BackofficeAlertService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Subscriptions\Domain\Events\ReactivationRequested;

class SendReactivationRequestNotificationToBackoffice implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(ReactivationRequested $event): void
    {
        $request = $event->request;

        app(BackofficeAlertService::class)->reactivationRequested(
            clinic: $request->clinic,
            requestId: (int) $request->id,
            requesterName: $request->requester?->name,
            motive: (string) ($request->comments ?? ''),
        );
    }
}
