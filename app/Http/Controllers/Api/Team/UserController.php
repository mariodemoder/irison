<?php

namespace App\Http\Controllers\Api\Team;

use App\Http\Controllers\Controller;
use App\Http\Requests\Team\StoreUserRequest;
use App\Http\Requests\Team\UpdateUserRequest;
use App\Models\User;
use App\Services\Team\TeamUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class UserController extends Controller
{
    public function __construct(private readonly TeamUserService $teamUserService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('team-access');

        $clinicId = (int) Auth::user()->clinic_id;

        return response()->json($this->teamUserService->index($request->all(), $clinicId));
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        Gate::authorize('team-access');

        $clinicId = (int) Auth::user()->clinic_id;
        $result = $this->teamUserService->store($request->validated(), $clinicId);

        return response()->json($result['payload'], $result['status']);
    }

    public function show(User $user): JsonResponse
    {
        Gate::authorize('team-access');

        return response()->json($this->teamUserService->show($user));
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        Gate::authorize('team-access');

        $clinicId = (int) Auth::user()->clinic_id;
        $result = $this->teamUserService->update($user, $request->validated(), $clinicId);

        return response()->json($result['payload'], $result['status']);
    }

    public function destroy(User $user): JsonResponse
    {
        Gate::authorize('team-access');

        $result = $this->teamUserService->destroy($user);

        return response()->json($result['payload'], $result['status']);
    }
}
