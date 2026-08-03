<?php

declare(strict_types=1);

namespace Modules\Finance\Infrastructure\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Modules\Finance\Application\DTOs\ExpenseFilterData;
use Modules\Finance\Application\UseCases\CreateExpenseCommand;
use Modules\Finance\Application\UseCases\DeleteExpenseCommand;
use Modules\Finance\Application\UseCases\ListExpensesQuery;
use Modules\Finance\Application\UseCases\ShowExpenseDetailQuery;
use Modules\Finance\Application\UseCases\UpdateExpenseCommand;
use Modules\Finance\Infrastructure\Persistence\ExpenseEloquentModel;
use Modules\Finance\Infrastructure\Requests\StoreExpenseRequest;
use Modules\Finance\Infrastructure\Requests\UpdateExpenseRequest;

class ExpenseController extends Controller
{
    public function __construct(
        private readonly ListExpensesQuery $listQuery,
        private readonly ShowExpenseDetailQuery $showQuery,
        private readonly CreateExpenseCommand $createCommand,
        private readonly UpdateExpenseCommand $updateCommand,
        private readonly DeleteExpenseCommand $deleteCommand,
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', ExpenseEloquentModel::class);

        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer'],
            'payment_method' => ['nullable', 'in:cash,card,transfer'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json($this->listQuery->execute(
            (int) Auth::user()->clinic_id,
            ExpenseFilterData::fromRequest($validated),
        ));
    }

    public function store(StoreExpenseRequest $request): JsonResponse
    {
        Gate::authorize('create', ExpenseEloquentModel::class);

        $expense = $this->createCommand->execute(
            (int) Auth::user()->clinic_id,
            $request->validated(),
        );

        return response()->json(['data' => $expense], 201);
    }

    public function show(int $expense): JsonResponse
    {
        $model = $this->findScopedModel($expense);
        Gate::authorize('view', $model);

        return response()->json(['data' => $this->showQuery->execute($expense, (int) Auth::user()->clinic_id)]);
    }

    public function update(int $expense, UpdateExpenseRequest $request): JsonResponse
    {
        $model = $this->findScopedModel($expense);
        Gate::authorize('update', $model);

        $expense = $this->updateCommand->execute($expense,
            (int) Auth::user()->clinic_id,
            $request->validated(),
        );

        return response()->json(['data' => $expense]);
    }

    public function destroy(int $expense): JsonResponse
    {
        $model = $this->findScopedModel($expense);
        Gate::authorize('delete', $model);

        $this->deleteCommand->execute($expense, (int) Auth::user()->clinic_id);

        return response()->json(['message' => 'Gasto eliminado.']);
    }

    private function findScopedModel(int $id): ExpenseEloquentModel
    {
        return ExpenseEloquentModel::where('clinic_id', Auth::user()->clinic_id)->findOrFail($id);
    }
}