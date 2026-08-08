<?php

namespace Modules\Subscriptions\Application\Services;

use App\Models\SubscriptionRequest;
use Modules\Subscriptions\Domain\Events\ReactivationRequested;
use Modules\Subscriptions\Domain\Events\UpgradeRequested;

class SubscriptionRequestService
{
    public function createRequest(array $data, int $clinicId, int $requestedBy): SubscriptionRequest
    {
        $request = SubscriptionRequest::create([
            'clinic_id' => $clinicId,
            'current_plan' => $data['current_plan'],
            'requested_plan' => $data['requested_plan'],
            'comments' => $data['comments'] ?? null,
            'requested_by' => $requestedBy,
            'status' => 'pending',
        ]);

        event(new UpgradeRequested($request));

        return $request;
    }

    public function createReactivationRequest(int $clinicId, int $requestedBy, string $comments, string $currentPlan = 'basic'): SubscriptionRequest
    {
        $request = SubscriptionRequest::create([
            'clinic_id' => $clinicId,
            'type' => SubscriptionRequest::TYPE_REACTIVATION,
            'current_plan' => $currentPlan,
            'requested_plan' => '',
            'comments' => $comments,
            'requested_by' => $requestedBy,
            'status' => 'pending',
        ]);

        event(new ReactivationRequested($request));

        return $request;
    }

    public function approveRequest(
        SubscriptionRequest $request,
        ?int $reviewedBy = null,
        ?string $reviewerComments = null,
    ): void
    {
        $request->status = 'waiting_payment';
        $request->reviewed_by = $reviewedBy;
        $request->reviewed_at = now();
        $request->reviewer_comments = $reviewerComments;
        $request->save();
    }

    public function rejectRequest(SubscriptionRequest $request, string $comments, int $reviewedBy): void
    {
        $request->status = 'rejected';
        $request->reviewer_comments = $comments;
        $request->reviewed_by = $reviewedBy;
        $request->reviewed_at = now();
        $request->save();
    }

    public function markAsPaid(SubscriptionRequest $request): void
    {
        $request->status = 'paid';
        $request->save();
    }

    public function completeSubscription(SubscriptionRequest $request): void
    {
        $request->status = 'completed';
        $request->completed_at = now();
        $request->save();
    }

    public function generateCheckoutUrl(SubscriptionRequest $request, array $checkoutData): void
    {
        $request->stripe_checkout_session_id = $checkoutData['session_id'];
        $request->checkout_url = $checkoutData['url'];
        $request->save();
    }
}