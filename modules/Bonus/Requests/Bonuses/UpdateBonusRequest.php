<?php

declare(strict_types=1);

namespace Modules\Bonus\Requests\Bonuses;

use App\Rules\ValidateDateAfterNow;
use App\Rules\ValidatePaymentAmount;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBonusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

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
