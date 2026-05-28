<?php

declare(strict_types=1);

namespace App\Http\Requests\Backoffice;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClinicRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user('admin');
    }

    public function rules(): array
    {
        $clinic = $this->route('clinic');
        $clinicId = is_object($clinic) ? (int) $clinic->id : 0;

        return [
            'name' => ['required', 'string', 'max:190'],
            'slug' => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9-]+$/', Rule::unique('clinics', 'slug')->ignore($clinicId)],
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:80'],
            'plan' => ['required', Rule::in(['basic', 'pro', 'enterprise'])],
        ];
    }
}
