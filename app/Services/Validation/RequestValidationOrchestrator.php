<?php

declare(strict_types=1);

namespace App\Services\Validation;

use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class RequestValidationOrchestrator
{
    public function validate(Request $request, string $context): array
    {
        return match ($context) {
            'appointments.store' => $this->validateAppointmentStore($request),
            default => [
                'ok' => true,
                'data' => $request->all(),
            ],
        };
    }

    private function validateAppointmentStore(Request $request): array
    {
        $clinicId = currentClinicId();
        $input = $request->all();
        $kind = $this->resolveAppointmentKind($input);

        $typeRules = [
            'nullable',
            'integer',
            Rule::exists('appointment_types', 'id')
                ->when(
                    $clinicId !== null,
                    fn ($rule) => $rule->where(fn ($query) => $query->where('clinic_id', (int) $clinicId))
                ),
        ];

        $rules = [
            'app_type_id' => $typeRules,
            'custom_type' => ['nullable', 'string', 'max:255'],
        ];

        if ($kind === 'typed') {
            $rules['app_type_id'][] = 'required';
        } else {
            $rules['custom_type'][] = 'required';
        }

        $messages = [
            'app_type_id.required' => 'Debes seleccionar un tipo de cita o indicar un nombre.',
            'custom_type.required' => 'Debes indicar un nombre para la cita si no seleccionas un tipo.',
            'app_type_id.exists' => 'El tipo de cita seleccionado no es válido para esta clínica.',
        ];

        $validator = Validator::make($input, $rules, $messages);

        if ($validator->fails()) {
            return $this->invalidResponse($validator);
        }

        $validated = $validator->validated();
        $validated['custom_type'] = isset($validated['custom_type'])
            ? trim((string) $validated['custom_type'])
            : null;

        if ($kind === 'typed') {
            $validated['custom_type'] = null;
        } else {
            $validated['app_type_id'] = null;
        }

        return [
            'ok' => true,
            'data' => array_merge($input, $validated),
            'kind' => $kind,
        ];
    }

    private function resolveAppointmentKind(array $input): string
    {
        $hasTypeId = isset($input['app_type_id'])
            && $input['app_type_id'] !== null
            && $input['app_type_id'] !== '';

        return $hasTypeId ? 'typed' : 'custom';
    }

    private function invalidResponse(ValidatorContract $validator): array
    {
        return [
            'ok' => false,
            'status' => 422,
            'response' => response()->json([
                'message' => 'Los datos enviados no son válidos.',
                'errors' => $validator->errors(),
            ], 422),
        ];
    }
}
