<?php

declare(strict_types=1);

namespace Modules\Finance\Infrastructure\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Modules\Finance\Application\UseCases\CreateExpenseCategoryCommand;
use Modules\Finance\Application\UseCases\DeleteExpenseCategoryCommand;
use Modules\Finance\Application\UseCases\ListExpenseCategoriesQuery;
use Modules\Finance\Application\UseCases\UpdateExpenseCategoryCommand;
use Modules\Finance\Infrastructure\Persistence\ExpenseCategoryEloquentModel;
use Modules\Finance\Infrastructure\Requests\StoreExpenseCategoryRequest;
use Modules\Finance\Infrastructure\Requests\UpdateExpenseCategoryRequest;

class ExpenseCategoryController extends Controller
{
    public function __construct(
        private readonly ListExpenseCategoriesQuery $listQuery,
        private readonly CreateExpenseCategoryCommand $createCommand,
        private readonly UpdateExpenseCategoryCommand $updateCommand,
        private readonly DeleteExpenseCategoryCommand $deleteCommand,
    ) {}

    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', ExpenseCategoryEloquentModel::class);

        return response()->json([
            'data' => $this->listQuery->execute((int) Auth::user()->clinic_id),
        ]);
    }

    public function store(StoreExpenseCategoryRequest $request): JsonResponse
    {
        Gate::authorize('create', ExpenseCategoryEloquentModel::class);

        $category = $this->createCommand->execute(
            (int) Auth::user()->clinic_id,
            $request->validated(),
        );

        return response()->json(['data' => $category], 201);
    }

    public function update(int $category, UpdateExpenseCategoryRequest $request): JsonResponse
    {
        $model = $this->findScopedModel($category);
        Gate::authorize('update', $model);

        $category = $this->updateCommand->execute($category,
            (int) Auth::user()->clinic_id,
            $request->validated(),
        );

        return response()->json(['data' => $category]);
    }

    public function destroy(int $category): JsonResponse
    {
        $model = $this->findScopedModel($category);
        Gate::authorize('delete', $model);

        $this->deleteCommand->execute($category, (int) Auth::user()->clinic_id);

        return response()->json(['message' => 'Categoría eliminada.']);
    }

    private function findScopedModel(int $id): ExpenseCategoryEloquentModel
    {
        return ExpenseCategoryEloquentModel::where('clinic_id', Auth::user()->clinic_id)->findOrFail($id);
    }
}