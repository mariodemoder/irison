<?php

declare(strict_types=1);

namespace App\Http\Requests\Backoffice;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClinicActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user('admin');
    }

    public function rules(): array
    {
        return [
            'days' => ['nullable', 'integer', 'min:1', 'max:60'],
            'reason' => ['nullable', 'string', 'max:500'],
            'plan' => ['nullable', Rule::in(['basic', 'pro', 'enterprise'])],
        ];
    }
}
