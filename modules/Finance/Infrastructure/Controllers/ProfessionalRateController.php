<?php

declare(strict_types=1);

namespace Modules\Finance\Infrastructure\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Modules\Finance\Application\UseCases\ListProfessionalRatesQuery;
use Modules\Finance\Application\UseCases\SaveProfessionalRateCommand;
use Modules\Finance\Infrastructure\Persistence\ProfessionalRateEloquentModel;
use Modules\Finance\Infrastructure\Requests\SaveProfessionalRateRequest;

class ProfessionalRateController extends Controller
{
    public function __construct(
        private readonly ListProfessionalRatesQuery $listQuery,
        private readonly SaveProfessionalRateCommand $saveCommand,
    ) {}

    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', ProfessionalRateEloquentModel::class);

        return response()->json([
            'data' => $this->listQuery->execute((int) Auth::user()->clinic_id),
        ]);
    }

    public function update(int $user, SaveProfessionalRateRequest $request): JsonResponse
    {
        $clinicId = (int) Auth::user()->clinic_id;

        $targetUser = User::where('clinic_id', $clinicId)->findOrFail($user);

        if ($targetUser->isOwner()) {
            return response()->json(['message' => 'No se puede asignar un coste por hora al propietario.'], 422);
        }

        $rateModel = ProfessionalRateEloquentModel::where('clinic_id', $clinicId)
            ->where('user_id', $targetUser->id)
            ->first()
            ?? new ProfessionalRateEloquentModel([
                'clinic_id' => $clinicId,
                'user_id' => $targetUser->id,
            ]);

        Gate::authorize('update', $rateModel);

        $rate = $this->saveCommand->execute(
            $clinicId,
            $targetUser->id,
            (float) ($request->validated()['cost_per_hour'] ?? 0),
            (bool) ($request->validated()['remove'] ?? false),
        );

        return response()->json(['data' => $rate]);
    }
}