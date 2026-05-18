<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidateInvoiceStatusTransition implements ValidationRule
{
    /**
     * Valida que la transición de estado sea válida.
     * Estados permitidos: draft → issued → cancelled
     * 
     * Uso: 'status' => ['sometimes', new ValidateInvoiceStatusTransition($currentStatus)]
     */
    private ?string $currentStatus;

    public function __construct(?string $currentStatus = null)
    {
        $this->currentStatus = $currentStatus;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $newStatus = strtolower($value);
        $allowedStatuses = ['draft', 'issued', 'cancelled'];

        if (!in_array($newStatus, $allowedStatuses, true)) {
            $fail("El estado '{$newStatus}' no es válido. Estados permitidos: " . implode(', ', $allowedStatuses));
            return;
        }

        if ($this->currentStatus === null) {
            return; // Sin estado actual, permitir cualquier transición (creación)
        }

        $currentStatus = strtolower($this->currentStatus);
        
        // Definir transiciones válidas
        $validTransitions = [
            'draft' => ['draft', 'issued', 'cancelled'],
            'issued' => ['issued', 'cancelled'],
            'cancelled' => ['cancelled'], // No se puede volver desde cancelado
        ];

        if (!isset($validTransitions[$currentStatus])) {
            $fail("El estado actual '{$currentStatus}' no es reconocido.");
            return;
        }

        if (!in_array($newStatus, $validTransitions[$currentStatus], true)) {
            $allowed = implode(', ', $validTransitions[$currentStatus]);
            $fail("No se puede cambiar de '{$currentStatus}' a '{$newStatus}'. Transiciones válidas: {$allowed}");
        }
    }
}
