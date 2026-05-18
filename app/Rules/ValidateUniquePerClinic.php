<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

class ValidateUniquePerClinic implements ValidationRule
{
    /**
     * Valida que un valor sea único dentro del scope de una clínica.
     * Equivalente a: unique:table,column,null,id,clinic_id,{clinic_id}
     * 
     * Uso: 'name' => [new ValidateUniquePerClinic('appointment_types', 'name', $clinicId, $ignoredId)]
     */
    private string $table;
    private string $column;
    private int $clinicId;
    private ?int $ignoredId;

    public function __construct(string $table, string $column, int $clinicId, ?int $ignoredId = null)
    {
        $this->table = $table;
        $this->column = $column;
        $this->clinicId = $clinicId;
        $this->ignoredId = $ignoredId;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $query = DB::table($this->table)
            ->where('clinic_id', $this->clinicId)
            ->where($this->column, $value);

        if ($this->ignoredId) {
            $query->where('id', '!=', $this->ignoredId);
        }

        if ($query->exists()) {
            $fail("El valor '{$value}' ya existe en esta clínica para el campo {$attribute}.");
        }
    }
}
