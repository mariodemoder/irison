<?php

declare(strict_types=1);

namespace Modules\Finance\Infrastructure\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreManualIncomeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['required', Rule::in(['cash', 'card', 'transfer'])],
            'description' => ['nullable', 'string', 'max:255'],
            'professional_id' => ['nullable', 'integer', 'exists:users,id'],
            'patient_id' => ['nullable', 'integer', 'exists:patients,id'],
            'date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
