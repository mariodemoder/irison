<?php

declare(strict_types=1);

namespace Modules\Finance\Infrastructure\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Modules\Finance\Application\UseCases\BuildFinanceSummaryQuery;
use Modules\Finance\Infrastructure\Persistence\ExpenseEloquentModel;

class FinanceSummaryController extends Controller
{
    public function __construct(
        private readonly BuildFinanceSummaryQuery $summaryQuery,
    ) {}

    public function show(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', ExpenseEloquentModel::class);

        $validated = $request->validate([
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date'],
        ]);

        $summary = $this->summaryQuery->execute(
            (int) Auth::user()->clinic_id,
            $validated['from_date'] ?? null,
            $validated['to_date'] ?? null,
        );

        return response()->json(['data' => $summary->toArray()]);
    }
}
