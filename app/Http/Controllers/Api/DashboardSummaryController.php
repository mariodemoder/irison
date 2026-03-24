<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\DashboardSummaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DashboardSummaryController extends Controller
{
    public function __construct(private readonly DashboardSummaryService $dashboardSummaryService)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $block = strtolower((string) $request->query('block', 'all'));
        if (!in_array($block, ['all', 'cards', 'alerts'], true)) {
            $block = 'all';
        }

        $clinicId = (int) (currentClinicId() ?? 0);
        $userId = (int) ($request->user()?->id ?? 0);
        $today = now()->toDateString();

        $cacheKey = sprintf(
            'dashboard:summary:v1:clinic:%d:user:%d:date:%s:block:%s',
            $clinicId,
            $userId,
            $today,
            $block
        );

        $payload = Cache::remember($cacheKey, now()->addSeconds(20), function () use ($block) {
            if ($block === 'cards') {
                return $this->dashboardSummaryService->cards();
            }

            if ($block === 'alerts') {
                return $this->dashboardSummaryService->alerts();
            }

            return $this->dashboardSummaryService->summary();
        });

        return response()->json($payload);
    }
}
