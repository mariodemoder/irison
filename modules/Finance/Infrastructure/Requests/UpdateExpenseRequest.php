<?php

declare(strict_types=1);

namespace Modules\Finance\Infrastructure\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'concept' => ['sometimes', 'required', 'string', 'max:255'],
            'category_id' => ['sometimes', 'nullable', 'integer', 'exists:expense_categories,id'],
            'provider_id' => ['sometimes', 'nullable', 'integer', 'exists:providers,id'],
            'supplier' => ['sometimes', 'nullable', 'string', 'max:255'],
            'amount' => ['sometimes', 'required', 'numeric', 'min:0'],
            'tax_rate' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'date' => ['sometimes', 'nullable', 'date'],
            'payment_method' => ['sometimes', 'nullable', Rule::in(['cash', 'card', 'transfer'])],
            'receipt_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
