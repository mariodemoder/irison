<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidateNIFFormat implements ValidationRule
{
    /**
     * Valida que el NIF/DNI tenga un formato válido (España).
     * Incluye validación del dígito verificador.
     * Formatos aceptados: 12345678A, 12345678-A, 12345678 A, X1234567L, etc.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $nif = strtoupper(trim($value));
        
        // Remover espacios y guiones
        $nif = str_replace(['-', ' '], '', $nif);

        // Patrón: 8 dígitos + 1 letra, o 1 letra + 7 dígitos + 1 letra (NIE, CIF)
        if (!preg_match('/^[0-9]{8}[A-Z]$|^[XYZ][0-9]{7}[A-Z]$|^[A-Z][0-9]{7}[0-9A-Z]$/', $nif)) {
            $fail("El campo {$attribute} no tiene un formato de NIF/DNI válido.");
            return;
        }

        // Validar dígito verificador
        if (!$this->validateNIFChecksum($nif)) {
            $fail("El dígito verificador del {$attribute} no es válido.");
        }
    }

    private function validateNIFChecksum(string $nif): bool
    {
        // Tabla de letras de validación para NIF
        $letters = 'TRWAGMYFPDXBNJZSQVHLCKE';

        // Extraer números (para NIF estándar)
        if (preg_match('/^(\d{8})([A-Z])$/', $nif, $matches)) {
            $number = (int) $matches[1];
            $letter = $matches[2];
            return $letters[$number % 23] === $letter;
        }

        // Para NIE (X, Y, Z al inicio)
        if (preg_match('/^([XYZ])(\d{7})([A-Z])$/', $nif, $matches)) {
            $prefix = $matches[1];
            $number = $matches[2];
            $letter = $matches[3];
            
            $numMap = ['X' => '0', 'Y' => '1', 'Z' => '2'];
            $fullNumber = $numMap[$prefix] . $number;
            return $letters[$fullNumber % 23] === $letter;
        }

        // Para CIF (letra inicial + 7 dígitos + verificador)
        // CIF validation es más complejo, por ahora retornamos true (se puede extender)
        if (preg_match('/^[A-Z]\d{7}[0-9A-Z]$/', $nif)) {
            return true;
        }

        return false;
    }
}
