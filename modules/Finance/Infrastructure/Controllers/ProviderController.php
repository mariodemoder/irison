<?php

declare(strict_types=1);

namespace Modules\Finance\Infrastructure\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Modules\Finance\Application\UseCases\CreateProviderCommand;
use Modules\Finance\Application\UseCases\DeleteProviderCommand;
use Modules\Finance\Application\UseCases\ListProvidersQuery;
use Modules\Finance\Application\UseCases\UpdateProviderCommand;
use Modules\Finance\Infrastructure\Persistence\ProviderEloquentModel;
use Modules\Finance\Infrastructure\Requests\StoreProviderRequest;
use Modules\Finance\Infrastructure\Requests\UpdateProviderRequest;

class ProviderController extends Controller
{
    public function __construct(
        private readonly ListProvidersQuery $listQuery,
        private readonly CreateProviderCommand $createCommand,
        private readonly UpdateProviderCommand $updateCommand,
        private readonly DeleteProviderCommand $deleteCommand,
    ) {}

    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', ProviderEloquentModel::class);

        return response()->json([
            'data' => $this->listQuery->execute((int) Auth::user()->clinic_id),
        ]);
    }

    public function store(StoreProviderRequest $request): JsonResponse
    {
        Gate::authorize('create', ProviderEloquentModel::class);

        $provider = $this->createCommand->execute(
            (int) Auth::user()->clinic_id,
            $request->validated(),
        );

        return response()->json(['data' => $provider], 201);
    }

    public function update(int $provider, UpdateProviderRequest $request): JsonResponse
    {
        $model = $this->findScopedModel($provider);
        Gate::authorize('update', $model);

        $provider = $this->updateCommand->execute(
            $provider,
            (int) Auth::user()->clinic_id,
            $request->validated(),
        );

        return response()->json(['data' => $provider]);
    }

    public function destroy(int $provider): JsonResponse
    {
        $model = $this->findScopedModel($provider);
        Gate::authorize('delete', $model);

        $this->deleteCommand->execute($provider, (int) Auth::user()->clinic_id);

        return response()->json(['message' => 'Proveedor eliminado.']);
    }

    private function findScopedModel(int $id): ProviderEloquentModel
    {
        return ProviderEloquentModel::where('clinic_id', Auth::user()->clinic_id)->findOrFail($id);
    }
}
