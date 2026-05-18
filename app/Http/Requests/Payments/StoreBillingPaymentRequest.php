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
            'amount' => [
                'required',
                'numeric',
                new ValidatePaymentAmount(),
            ],
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
            'amount.required' => 'El monto es requerido.',
            'amount.numeric' => 'El monto debe ser un número.',
            'appointment_id.exists' => 'La cita no existe o no pertenece a tu clínica.',
            'package_id.exists' => 'El paquete no existe o no pertenece a tu clínica.',
        ];
    }
}
