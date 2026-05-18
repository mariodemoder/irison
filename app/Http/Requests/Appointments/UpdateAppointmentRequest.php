<?php

namespace App\Http\Requests\Appointments;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Rules\ValidateDateAfterNow;
use App\Rules\ValidateTimeRange;
use App\Rules\ValidateSlotAvailability;

class UpdateAppointmentRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $date = $this->input('date');
        $startTime = $this->normalizeTimeInput($this->input('start_time'));
        $endTime = $this->normalizeTimeInput($this->input('end_time'));

        if (!$date) {
            $date = $this->extractDateInput($this->input('start_time'))
                ?? $this->extractDateInput($this->input('end_time'));
        }

        $this->merge(array_filter([
            'date' => $date,
            'start_time' => $startTime,
            'end_time' => $endTime,
        ], static fn ($value) => $value !== null && $value !== ''));
    }

    public function authorize(): bool
    {
        return true; // Autorización manejada por Policy
    }

    /**
     * Reglas de validación para actualizar una cita.
     * Los campos son 'sometimes' para permitir actualizaciones parciales.
     */
    public function rules(): array
    {
        $appointmentId = $this->route('appointment')?->id;
        $clinicId = (int) (currentClinicId() ?? $this->user()?->clinic_id ?? 0);

        return [
            'date' => ['sometimes', 'required', 'date', new ValidateDateAfterNow()],
            'start_time' => ['bail', 'sometimes', 'required', 'date_format:H:i'],
            'end_time' => [
                'bail',
                'sometimes',
                'required',
                'date_format:H:i',
                new ValidateTimeRange($this->input('start_time')),
                new ValidateSlotAvailability($this->input('date'), $clinicId, $appointmentId, $this->input('start_time')),
            ],
            'patient_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('patients', 'id')->where('clinic_id', $clinicId),
            ],
            'app_type_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('appointment_types', 'id')->where('clinic_id', $clinicId),
            ],
            'custom_type' => ['sometimes', 'nullable', 'string', 'max:255'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->input('kind') === 'typed' && !$this->input('app_type_id')) {
                $validator->errors()->add('app_type_id', 'El tipo de cita es requerido cuando kind es "typed".');
            }

            if ($this->input('kind') === 'custom' && !$this->input('custom_type')) {
                $validator->errors()->add('custom_type', 'El tipo personalizado es requerido cuando kind es "custom".');
            }
        });
    }

    public function messages(): array
    {
        return [
            'date.date' => 'La fecha debe ser una fecha válida.',
            'start_time.date_format' => 'La hora debe tener el formato HH:mm.',
            'end_time.date_format' => 'La hora debe tener el formato HH:mm.',
            'patient_id.exists' => 'El paciente no existe o no pertenece a tu clínica.',
        ];
    }

    private function normalizeTimeInput(mixed $value): mixed
    {
        if (!is_string($value) || trim($value) === '') {
            return $value;
        }

        $trimmed = trim($value);

        if (preg_match('/^\d{2}:\d{2}$/', $trimmed) === 1) {
            return $trimmed;
        }

        try {
            return Carbon::parse($trimmed)->format('H:i');
        } catch (\Throwable) {
            return $value;
        }
    }

    private function extractDateInput(mixed $value): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse(trim($value))->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
