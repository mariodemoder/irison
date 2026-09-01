<?php

namespace Modules\PatientPortal\Infrastructure\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\PatientPortal\Application\Services\PatientProfileService;
use Modules\PatientPortal\Application\DTOs\ProfileUpdateDTO;

class PatientProfileController extends Controller
{
    public function __construct(
        private PatientProfileService $profileService
    ) {}

    public function index(Request $request)
    {
        $patient = $this->profileService->get($request->user());

        return response()->json(['patient' => $patient]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:50',
            'address' => 'sometimes|string|max:255',
            'zip' => 'sometimes|string|max:20',
            'city' => 'sometimes|string|max:100',
            'province' => 'sometimes|string|max:100',
            'country' => 'sometimes|string|max:100',
        ]);

        $dto = ProfileUpdateDTO::fromRequest($request);
        $patient = $this->profileService->update(
            $request->user(),
            $dto,
            $request->ip(),
            $request->userAgent()
        );

        return response()->json(['patient' => $patient]);
    }
}
