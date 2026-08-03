<?php

declare(strict_types=1);

namespace Modules\Finance\Infrastructure\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveProfessionalRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cost_per_hour' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'remove' => ['nullable', 'boolean'],
        ];
    }
}
