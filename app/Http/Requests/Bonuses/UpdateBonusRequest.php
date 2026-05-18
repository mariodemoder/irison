<?php

namespace App\Http\Requests\Bonuses;

use Illuminate\Foundation\Http\FormRequest;
use App\Rules\ValidateDateAfterNow;
use App\Rules\ValidatePaymentAmount;

class UpdateBonusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Autorización manejada por Policy
    }

    /**
     * Reglas de validación para actualizar un bono.
     */
    public function rules(): array
    {
        $total = $this->input('total_sessions') ?? $this->route('bonus')?->total_sessions ?? 0;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'total_sessions' => ['sometimes', 'required', 'integer', 'min:1'],
            'price' => ['sometimes', 'nullable', 'numeric', new ValidatePaymentAmount()],
            'expires_at' => ['sometimes', 'nullable', 'date', new ValidateDateAfterNow()],
            'remaining_sessions' => ['sometimes', 'integer', 'min:0', 'max:' . $total],
        ];
    }

    public function messages(): array
    {
        return [
            'remaining_sessions.max' => 'Las sesiones restantes no pueden exceder el total de sesiones.',
        ];
    }
}
