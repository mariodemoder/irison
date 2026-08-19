<?php

declare(strict_types=1);

namespace Modules\Finance\Infrastructure\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Modules\Finance\Application\UseCases\ListIncomePaymentsQuery;
use Modules\Finance\Application\UseCases\RegisterManualIncomeCommand;
use Modules\Finance\Application\UseCases\RefundPaymentCommand;
use Modules\Finance\Infrastructure\Requests\StoreManualIncomeRequest;
use Modules\Finance\Infrastructure\Requests\RefundPaymentRequest;

class IncomeController extends Controller
{
    public function __construct(
        private readonly ListIncomePaymentsQuery $listQuery,
        private readonly RegisterManualIncomeCommand $createCommand,
        private readonly RefundPaymentCommand $refundCommand,
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Payment::class);

        $validated = $request->validate([
            'professional_id' => ['nullable', 'integer'],
            'method' => ['nullable', 'string', 'in:cash,card,transfer'],
            'concept' => ['nullable', 'string', 'in:appointment,package,credit,other'],
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

    public function store(StoreManualIncomeRequest $request): JsonResponse
    {
        Gate::authorize('create', Payment::class);

        $result = $this->createCommand->execute(
            (int) Auth::user()->clinic_id,
            $request->validated(),
        );

        return response()->json(['data' => $result->toArray()], 201);
    }

    public function refund(RefundPaymentRequest $request, int $payment): JsonResponse
    {
        $paymentModel = Payment::where('id', $payment)
            ->where('clinic_id', (int) Auth::user()->clinic_id)
            ->firstOrFail();

        Gate::authorize('refund', $paymentModel);

        $result = $this->refundCommand->execute(
            (int) Auth::user()->clinic_id,
            $payment,
            $request->validated(),
        );

        return response()->json([
            'data' => [
                'payment' => $result['payment']->toArray(),
                'abono' => $result['abono'],
            ],
        ]);
    }
}
