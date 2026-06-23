<?php

namespace App\Http\Requests\Team;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $clinicId = (int) (auth()->user()->clinic_id ?? 0);

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('users', 'email')->where(fn ($q) => $q->where('clinic_id', $clinicId)),
            ],
            'password' => ['required', 'string', 'min:8'],
            'profile_id' => ['required', 'integer', 'exists:profiles,id'],
            'profession_id' => ['nullable', 'integer', 'exists:professions,id'],
            'allow_online_booking' => ['nullable', 'boolean'],
            'allow_manage_agenda' => ['nullable', 'boolean'],
            'schedules' => ['nullable', 'array'],
            'schedules.*.day_of_week' => ['required', 'integer', 'between:0,6'],
            'schedules.*.enabled' => ['required', 'boolean'],
            'schedules.*.start_time' => ['nullable', 'string'],
            'schedules.*.end_time' => ['nullable', 'string'],
            'schedule_exceptions' => ['nullable', 'array'],
            'schedule_exceptions.*.date' => ['required', 'string', 'regex:/^\d{4}-\d{2}-\d{2}(\.\.\d{4}-\d{2}-\d{2})?$/'],
            'schedule_exceptions.*.start_time' => ['nullable', 'string'],
            'schedule_exceptions.*.end_time' => ['nullable', 'string'],
            'schedule_exceptions.*.reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del usuario es requerido.',
            'email.required' => 'El email es requerido.',
            'email.unique' => 'Este email ya existe en tu clínica.',
            'password.required' => 'La contraseña es requerida.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'profile_id.required' => 'El perfil es requerido.',
            'profile_id.exists' => 'El perfil seleccionado no es válido.',
            'profession_id.exists' => 'La profesión seleccionada no es válida.',
        ];
    }
}
