<?php

namespace App\Http\Controllers\Api\Team;

use App\Http\Controllers\Controller;
use App\Services\Team\ProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ProfileController extends Controller
{
    public function __construct(private readonly ProfileService $profileService)
    {
    }

    public function index(): JsonResponse
    {
        Gate::authorize('team-access');

        return response()->json($this->profileService->index());
    }
}
