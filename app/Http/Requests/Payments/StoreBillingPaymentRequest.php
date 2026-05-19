<?php

namespace App\Http\Requests\Payments;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Rules\ValidatePaymentAmount;

class StoreBillingPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Autorización manejada por Policy
    }

    /**
     * Reglas de validación para crear un pago.
     */
    public function rules(): array
    {
        $clinicId = (int) (currentClinicId() ?? $this->user()?->clinic_id ?? 0);

        return [
            'patient_id' => [
                'required',
                'integer',
                Rule::exists('patients', 'id')->where('clinic_id', $clinicId),
            ],
            'concept' => ['required', Rule::in(['appointment', 'package', 'credit'])],
            'amount' => [
                'required',
                'numeric',
                new ValidatePaymentAmount(),
            ],
            'method' => ['required', Rule::in(['cash', 'card', 'transfer'])],
            'status' => ['required', Rule::in(['completed', 'pending', 'refunded'])],
            'appointment_id' => [
                'nullable',
                'integer',
                Rule::exists('appointments', 'id')->where('clinic_id', $clinicId),
            ],
            'package_id' => [
                'nullable',
                'integer',
                Rule::exists('bonuses', 'id')->where('clinic_id', $clinicId),
            ],
            'notes' => ['nullable', 'string', 'max:500'],
            'paid_at' => ['nullable', 'date'],
        ];
    }

    /**
     * Validaciones personalizadas posteriores a la validación de reglas.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Al menos uno de appointment_id o package_id debe estar presente
            if (!$this->input('appointment_id') && !$this->input('package_id')) {
                $validator->errors()->add(
                    'appointment_id',
                    'Debe especificar una cita o un paquete para asociar el pago.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'patient_id.required' => 'El paciente es requerido.',
            'patient_id.exists' => 'El paciente no existe o no pertenece a tu clínica.',
            'concept.required' => 'El concepto es requerido.',
            'concept.in' => 'El concepto seleccionado no es válido.',
            'amount.required' => 'El monto es requerido.',
            'amount.numeric' => 'El monto debe ser un número.',
            'method.required' => 'El método de pago es requerido.',
            'method.in' => 'El método de pago seleccionado no es válido.',
            'status.required' => 'El estado del pago es requerido.',
            'status.in' => 'El estado del pago seleccionado no es válido.',
            'appointment_id.exists' => 'La cita no existe o no pertenece a tu clínica.',
            'package_id.exists' => 'El paquete no existe o no pertenece a tu clínica.',
            'paid_at.date' => 'La fecha de pago debe ser válida.',
        ];
    }
}
