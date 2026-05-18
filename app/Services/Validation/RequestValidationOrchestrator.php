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
            'appointments.index' => $this->validateAppointmentIndex($request),
            'patients.index' => $this->validatePatientIndex($request),
            'documents.index' => $this->validateDocumentIndex($request),
            'bonuses.index' => $this->validateBonusIndex($request),
            'payments.index' => $this->validatePaymentIndex($request),
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

    private function validateAppointmentIndex(Request $request): array
    {
        $rules = [
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'status' => ['nullable', 'string', Rule::in('pending', 'completed', 'cancelled')],
            'patient_id' => ['nullable', 'integer', 'exists:patients,id'],
            'app_type_id' => ['nullable', 'integer', 'exists:appointment_types,id'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];

        $messages = [
            'date_from.date' => 'La fecha de inicio debe ser una fecha válida.',
            'date_to.date' => 'La fecha de fin debe ser una fecha válida.',
            'status.in' => 'El estado debe ser: pending, completed o cancelled.',
            'per_page.max' => 'El máximo de resultados por página es 100.',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            return $this->invalidResponse($validator);
        }

        return [
            'ok' => true,
            'data' => $validator->validated(),
        ];
    }

    private function validatePatientIndex(Request $request): array
    {
        $rules = [
            'q' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort_by' => ['nullable', 'string', Rule::in('name', 'email', 'phone', 'created_at')],
            'sort_order' => ['nullable', 'string', Rule::in('asc', 'desc')],
        ];

        $messages = [
            'q.max' => 'El término de búsqueda no puede exceder 255 caracteres.',
            'per_page.max' => 'El máximo de resultados por página es 100.',
            'sort_by.in' => 'El campo de ordenamiento no es válido.',
            'sort_order.in' => 'El orden debe ser asc (ascendente) o desc (descendente).',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            return $this->invalidResponse($validator);
        }

        return [
            'ok' => true,
            'data' => $validator->validated(),
        ];
    }

    private function validateDocumentIndex(Request $request): array
    {
        $rules = [
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', Rule::in('draft', 'issued', 'cancelled')],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];

        $messages = [
            'q.max' => 'El término de búsqueda no puede exceder 255 caracteres.',
            'status.in' => 'El estado debe ser: draft, issued o cancelled.',
            'from_date.date' => 'La fecha de inicio debe ser una fecha válida.',
            'to_date.date' => 'La fecha de fin debe ser una fecha válida.',
            'per_page.max' => 'El máximo de resultados por página es 100.',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            return $this->invalidResponse($validator);
        }

        return [
            'ok' => true,
            'data' => $validator->validated(),
        ];
    }

    private function validateBonusIndex(Request $request): array
    {
        $rules = [
            'patient_id' => ['nullable', 'integer', 'exists:patients,id'],
            'status' => ['nullable', 'string', Rule::in('active', 'expired')],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];

        $messages = [
            'status.in' => 'El estado debe ser: active o expired.',
            'per_page.max' => 'El máximo de resultados por página es 100.',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            return $this->invalidResponse($validator);
        }

        return [
            'ok' => true,
            'data' => $validator->validated(),
        ];
    }

    private function validatePaymentIndex(Request $request): array
    {
        $rules = [
            'patient_id' => ['nullable', 'integer', 'exists:patients,id'],
            'status' => ['nullable', 'string', Rule::in('pending', 'paid', 'failed')],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];

        $messages = [
            'status.in' => 'El estado debe ser: pending, paid o failed.',
            'from_date.date' => 'La fecha de inicio debe ser una fecha válida.',
            'to_date.date' => 'La fecha de fin debe ser una fecha válida.',
            'per_page.max' => 'El máximo de resultados por página es 100.',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            return $this->invalidResponse($validator);
        }

        return [
            'ok' => true,
            'data' => $validator->validated(),
        ];
    }
}
