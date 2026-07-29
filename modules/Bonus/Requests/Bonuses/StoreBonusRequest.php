<?php

declare(strict_types=1);

namespace Modules\Bonus\Requests\Bonuses;

use App\Rules\ValidateDateAfterNow;
use App\Rules\ValidatePaymentAmount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBonusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bonus_type_id' => [
                'nullable',
                'integer',
                Rule::exists('bonus_types', 'id')
                    ->where('clinic_id', (int) (currentClinicId() ?? 0))
                    ->whereNull('deleted_at'),
            ],
            'name' => ['required', 'string', 'max:255'],
            'total_sessions' => ['required', 'integer', 'min:1'],
            'price' => ['nullable', 'numeric', new ValidatePaymentAmount()],
            'expires_at' => ['nullable', 'date', new ValidateDateAfterNow()],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del bono es requerido.',
            'name.string' => 'El nombre debe ser texto.',
            'name.max' => 'El nombre no puede exceder 255 caracteres.',
            'total_sessions.required' => 'El número total de sesiones es requerido.',
            'total_sessions.integer' => 'El número de sesiones debe ser un número entero.',
            'total_sessions.min' => 'El número de sesiones debe ser al menos 1.',
            'price.numeric' => 'El precio debe ser un número.',
            'expires_at.date' => 'La fecha de expiración debe ser una fecha válida.',
        ];
    }
}
