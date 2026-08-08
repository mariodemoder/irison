<?php

namespace Modules\Subscriptions\Domain\Events;

use App\Models\SubscriptionRequest;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReactivationApproved
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly SubscriptionRequest $request,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('subscription-requests.' . $this->request->clinic_id);
    }
}
