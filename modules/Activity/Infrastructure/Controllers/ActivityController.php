<?php

declare(strict_types=1);

namespace Modules\Activity\Infrastructure\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Modules\Activity\Application\DTOs\ActivityFilterData;
use Modules\Activity\Application\UseCases\ListActivityQuery;
use Modules\Activity\Infrastructure\Persistence\ActivityLogQueryModel;

class ActivityController extends Controller
{
    public function __construct(
        private readonly ListActivityQuery $listQuery,
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', ActivityLogQueryModel::class);

        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'event' => ['nullable', 'string', 'max:100'],
            'user_id' => ['nullable', 'integer'],
            'entity' => ['nullable', 'string', 'max:50'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json($this->listQuery->execute(
            (int) Auth::user()->clinic_id,
            ActivityFilterData::fromRequest($validated),
        ));
    }
}