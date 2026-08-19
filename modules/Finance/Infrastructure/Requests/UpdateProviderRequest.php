<?php

declare(strict_types=1);

namespace Modules\Finance\Infrastructure\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProviderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $clinicId = $this->user()?->clinic_id;
        $providerId = (int) ($this->route('provider') ?? 0);

        return [
            'name' => [
                'sometimes', 'required', 'string', 'max:255',
                Rule::unique('providers', 'name')
                    ->where('clinic_id', $clinicId)
                    ->ignore($providerId),
            ],
            'nif' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
