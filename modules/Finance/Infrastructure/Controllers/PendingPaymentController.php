<?php

declare(strict_types=1);

namespace Modules\Finance\Infrastructure\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Modules\Finance\Application\UseCases\ListPendingPaymentsQuery;
use Modules\Finance\Application\UseCases\RegisterPaymentFromPendingCommand;
use Modules\Finance\Infrastructure\Persistence\ExpenseEloquentModel;

class PendingPaymentController extends Controller
{
    public function __construct(
        private readonly ListPendingPaymentsQuery $listQuery,
        private readonly RegisterPaymentFromPendingCommand $registerCommand,
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', ExpenseEloquentModel::class);

        $validated = $request->validate([
            'patient_id' => ['nullable', 'integer'],
            'professional_id' => ['nullable', 'integer'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        return response()->json($this->listQuery->execute(
            (int) Auth::user()->clinic_id,
            $validated,
        ));
    }

    public function registerPayment(Request $request, int $appointment): JsonResponse
    {
        Gate::authorize('viewAny', ExpenseEloquentModel::class);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['required', 'in:cash,card,transfer'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $result = $this->registerCommand->execute(
            (int) Auth::user()->clinic_id,
            $appointment,
            $validated,
        );

        return response()->json(['data' => $result]);
    }
}
