<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class SubscriptionController extends Controller
{
    public function show(): JsonResponse
    {
        $user = Auth::user();
        $clinic = $user->clinic;
        $plan = $clinic->plan ?? 'basic';
        $pricing = config('pricing');
        $planConfig = $pricing[$plan] ?? [];
        $maxUsers = $planConfig['users'] ?? 0;
        $userCount = $clinic->users()->count();

        $recentUpgrade = SubscriptionRequest::query()
            ->where('clinic_id', $clinic->id)
            ->where('status', 'completed')
            ->where('completed_at', '>=', now()->subHours(24))
            ->latest('completed_at')
            ->first();

        return response()->json([
            'data' => [
                'plan' => $plan,
                'plan_name' => $planConfig['name'] ?? 'Basic',
                'plan_price' => $planConfig['price'] ?? 29,
                'status' => $clinic->tenantStatus(),
                'max_users' => $maxUsers,
                'users_used' => $userCount,
                'next_plan' => $this->nextPlan($plan),
                'recent_completed_upgrade' => $recentUpgrade ? [
                    'id' => $recentUpgrade->id,
                    'current_plan' => $recentUpgrade->current_plan,
                    'requested_plan' => $recentUpgrade->requested_plan,
                    'completed_at' => $recentUpgrade->completed_at,
                ] : null,
            ],
        ]);
    }

    public function history(): JsonResponse
    {
        $requests = SubscriptionRequest::query()
            ->where('clinic_id', Auth::user()->clinic_id)
            ->with('reviewer:id,name')
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $requests]);
    }

    private function nextPlan(string $current): ?string
    {
        return match ($current) {
            'basic' => 'pro',
            'pro' => null,
            default => null,
        };
    }
}
