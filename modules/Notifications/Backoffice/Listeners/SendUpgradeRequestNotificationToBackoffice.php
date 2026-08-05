<?php

declare(strict_types=1);

namespace Modules\Notifications\Backoffice\Listeners;

use App\Events\UpgradeRequested;
use App\Services\Backoffice\BackofficeAlertService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendUpgradeRequestNotificationToBackoffice implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(UpgradeRequested $event): void
    {
        $request = $event->request;

        app(BackofficeAlertService::class)->upgradeRequested(
            clinic: $request->clinic,
            requestId: (int) $request->id,
            requestedPlan: (string) $request->requested_plan,
            requesterName: $request->requester?->name,
        );
    }
}
