<?php

namespace App\Http\Controllers\Api;

use App\Models\AppointmentType;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class AppointmentTypeController extends BaseController
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum', 'clinic']);
    }

    public function store(Request $request)
    {
        $clinicId = currentClinicId();

        $validated = $request->validate([
            'description' => [
                'required',
                'string',
                'max:255',
                Rule::unique('appointment_types')->where(function ($q) use ($clinicId) {
                    $q->where('clinic_id', $clinicId);
                }),
            ],
            'estimated_hours' => 'nullable|integer|min:0|max:23',
            'estimated_minutes' => 'nullable|integer|min:0|max:59',
            'price' => 'nullable|numeric|min:0',
        ]);

        $type = AppointmentType::create([
            'clinic_id' => $clinicId,
            'description' => $validated['description'],
            'estimated_hours' => (int) ($validated['estimated_hours'] ?? 0),
            'estimated_minutes' => (int) ($validated['estimated_minutes'] ?? 60),
            'price' => (float) ($validated['price'] ?? 0),
        ]);

        return response()->json([
            'data' => [
                'id' => $type->id,
                'description' => $type->description,
                'estimated_hours' => $type->estimated_hours,
                'estimated_minutes' => $type->estimated_minutes,
                'price' => $type->price,
            ]
        ], 201);
    }
}
