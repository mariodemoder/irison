<?php

namespace App\Http\Requests\Appointments;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Rules\ValidateDateAfterNow;
use App\Rules\ValidateTimeRange;
class StoreAppointmentRequest extends FormRequest
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
     * Reglas de validación para crear una cita.
     */
    public function rules(): array
    {
        $clinicId = (int) (currentClinicId() ?? $this->user()?->clinic_id ?? 0);

        return [
            'date' => ['required', 'date', new ValidateDateAfterNow()],
            'start_time' => ['bail', 'required', 'date_format:H:i'],
            'end_time' => [
                'bail',
                'required',
                'date_format:H:i',
                new ValidateTimeRange($this->input('start_time')),
            ],
            'patient_id' => [
                'required',
                'integer',
                Rule::exists('patients', 'id')->where('clinic_id', $clinicId),
            ],
            'app_type_id' => [
                'nullable',
                'integer',
                Rule::exists('appointment_types', 'id')->where('clinic_id', $clinicId),
            ],
            'custom_type' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'price' => ['required', 'numeric', 'min:0'],
            'payment_type' => ['nullable', 'string', 'in:single,bonus,credit'],
            'status' => ['nullable', 'string'],
            'use_bonus_id' => ['nullable', 'integer'],
            'bonus_notes' => ['nullable', 'string', 'max:500'],
            'apply_credit' => ['nullable', 'boolean'],
            'apply_credit_mode' => ['nullable', 'string'],
            'apply_credit_amount' => ['nullable', 'numeric', 'min:0'],
            'use_credit_payment_id' => ['nullable', 'integer'],
            'use_credit_amount' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * Validaciones personalizadas posteriores a la validación de reglas.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validar que si kind === 'typed', app_type_id sea requerido
            if ($this->input('kind') === 'typed' && !$this->input('app_type_id')) {
                $validator->errors()->add('app_type_id', 'El tipo de cita es requerido cuando kind es "typed".');
            }

            // Validar que si kind === 'custom', custom_type sea requerido
            if ($this->input('kind') === 'custom' && !$this->input('custom_type')) {
                $validator->errors()->add('custom_type', 'El tipo personalizado es requerido cuando kind es "custom".');
            }
        });
    }

    public function messages(): array
    {
        return [
            'date.required' => 'La fecha de la cita es requerida.',
            'date.date' => 'La fecha debe ser una fecha válida.',
            'start_time.required' => 'La hora de inicio es requerida.',
            'start_time.date_format' => 'La hora debe tener el formato HH:mm.',
            'end_time.required' => 'La hora de finalización es requerida.',
            'end_time.date_format' => 'La hora debe tener el formato HH:mm.',
            'patient_id.required' => 'El paciente es requerido.',
            'patient_id.exists' => 'El paciente no existe o no pertenece a tu clínica.',
            'app_type_id.exists' => 'El tipo de cita no existe.',
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
