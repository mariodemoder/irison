<?php

namespace App\Http\Controllers\Api\Team;

use App\Http\Controllers\Controller;
use App\Http\Requests\Team\StoreProfessionRequest;
use App\Http\Requests\Team\UpdateProfessionRequest;
use App\Models\Profession;
use App\Services\Team\ProfessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class ProfessionController extends Controller
{
    public function __construct(private readonly ProfessionService $professionService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('team-access');

        $clinicId = (int) Auth::user()->clinic_id;

        return response()->json($this->professionService->index($clinicId));
    }

    public function store(StoreProfessionRequest $request): JsonResponse
    {
        Gate::authorize('team-access');

        $clinicId = (int) Auth::user()->clinic_id;
        $result = $this->professionService->store($request->validated(), $clinicId);

        return response()->json($result['payload'], $result['status']);
    }

    public function update(UpdateProfessionRequest $request, Profession $profession): JsonResponse
    {
        Gate::authorize('team-access');

        $clinicId = (int) Auth::user()->clinic_id;
        $result = $this->professionService->update($profession, $request->validated(), $clinicId);

        return response()->json($result['payload'], $result['status']);
    }

    public function destroy(Profession $profession): JsonResponse
    {
        Gate::authorize('team-access');

        $result = $this->professionService->destroy($profession);

        return response()->json($result['payload'], $result['status']);
    }
}
