<?php

namespace App\Http\Requests\Api;

use App\Rules\ValidateNIFFormat;
use App\Rules\ValidatePhoneFormat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'clinic_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')],
            'password' => ['required', 'string', 'min:8'],
            'nif' => ['required', 'string', new ValidateNIFFormat],
            'zip' => ['required', 'string', 'regex:/^(0[1-9]|[1-4]\d|5[0-2])\d{3}$/'],
            'phone' => ['required', 'string', new ValidatePhoneFormat],
        ];
    }

    public function messages(): array
    {
        return [
            'nif.required' => 'El NIF/NIE es obligatorio.',
            'zip.required' => 'El código postal es obligatorio.',
            'zip.regex' => 'El código postal debe tener 5 dígitos (de 01xxx a 52xxx).',
            'phone.required' => 'El teléfono es obligatorio.',
            'email.unique' => 'El email ya está en uso.',
        ];
    }
}
