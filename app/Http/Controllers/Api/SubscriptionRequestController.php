<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\SubscriptionRequestMail;
use App\Models\SubscriptionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;

class SubscriptionRequestController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', SubscriptionRequest::class);

        $data = $request->validate([
            'requested_plan' => 'required|in:pro,enterprise',
            'comments' => 'nullable|string|max:2000',
        ]);

        $user = Auth::user();
        $clinic = $user->clinic;
        $currentPlan = $clinic->plan ?? 'basic';

        $subscriptionRequest = SubscriptionRequest::create([
            'clinic_id' => $clinic->id,
            'current_plan' => $currentPlan,
            'requested_plan' => $data['requested_plan'],
            'comments' => $data['comments'] ?? null,
            'requested_by' => $user->id,
            'status' => 'pending',
        ]);

        $recipient = trim((string) config('billing.subscription_request_notification_to', ''));
        if ($recipient && filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            Mail::to($recipient)->queue(new SubscriptionRequestMail($subscriptionRequest));
        }

        return response()->json(['data' => $subscriptionRequest], 201);
    }
}
