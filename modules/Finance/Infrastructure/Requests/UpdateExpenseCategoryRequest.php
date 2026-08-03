<?php

declare(strict_types=1);

namespace Modules\Finance\Infrastructure\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExpenseCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $clinicId = $this->user()?->clinic_id;
        $categoryId = (int) ($this->route('category') ?? 0);

        return [
            'name' => [
                'sometimes', 'required', 'string', 'max:150',
                Rule::unique('expense_categories', 'name')
                    ->where('clinic_id', $clinicId)
                    ->ignore($categoryId),
            ],
            'color' => ['sometimes', 'nullable', 'string', 'max:20'],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
