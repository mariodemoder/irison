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

        return response()->json([
            'data' => [
                'plan' => $plan,
                'plan_name' => $planConfig['name'] ?? 'Basic',
                'plan_price' => $planConfig['price'] ?? 29,
                'status' => $clinic->status ?? 'active',
                'max_users' => $maxUsers,
                'users_used' => $userCount,
                'next_plan' => $this->nextPlan($plan),
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
            'pro' => 'enterprise',
            default => null,
        };
    }
}
