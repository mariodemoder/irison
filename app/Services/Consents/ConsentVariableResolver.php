<?php

namespace App\Services\Consents;

use App\Models\Clinic;
use App\Models\Patient;
use App\Models\User;

class ConsentVariableResolver
{
    public const VARIABLES = [
        'paciente_nombre',
        'paciente_apellidos',
        'dni',
        'telefono',
        'email',
        'fecha',
        'profesional',
        'clinica',
        'tratamiento',
        'especialidad',
    ];

    public function resolve(string $content, Patient $patient, Clinic $clinic, ?User $user = null): array
    {
        $snapshot = [];
        $replacements = [];

        foreach (self::VARIABLES as $var) {
            $value = $this->resolveVariable($var, $patient, $clinic, $user);
            $snapshot[$var] = $value;
            $replacements['{' . $var . '}'] = $value;
        }

        $html = str_replace(array_keys($replacements), array_values($replacements), $content);

        return [
            'html' => $html,
            'snapshot' => $snapshot,
        ];
    }

    private function resolveVariable(string $variable, Patient $patient, Clinic $clinic, ?User $user = null): string
    {
        return match ($variable) {
            'paciente_nombre' => $patient->first_name ?? '',
            'paciente_apellidos' => $patient->last_name ?? '',
            'dni' => $patient->nif ?? '',
            'telefono' => $patient->phone ?? '',
            'email' => $patient->email ?? '',
            'fecha' => now()->format('d/m/Y'),
            'profesional' => $user?->name ?? '',
            'clinica' => $clinic->name ?? '',
            'tratamiento' => '',
            'especialidad' => $user?->profile?->name ?? '',
            default => '',
        };
    }

    public static function variableLabels(): array
    {
        return [
            '{paciente_nombre}' => 'Nombre del paciente',
            '{paciente_apellidos}' => 'Apellidos del paciente',
            '{dni}' => 'DNI/NIF del paciente',
            '{telefono}' => 'Teléfono del paciente',
            '{email}' => 'Email del paciente',
            '{fecha}' => 'Fecha actual',
            '{profesional}' => 'Nombre del profesional',
            '{clinica}' => 'Nombre de la clínica',
            '{tratamiento}' => 'Tratamiento (pendiente)',
            '{especialidad}' => 'Especialidad del profesional',
        ];
    }
}
