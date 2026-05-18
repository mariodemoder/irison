<?php

namespace App\Http\Requests\Documents;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Rules\ValidateInvoiceStatusTransition;

class UpdateDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Autorización manejada por Policy
    }

    /**
     * Reglas de validación para actualizar un documento (factura).
     */
    public function rules(): array
    {
        $currentStatus = $this->route('document')?->status;

        return [
            'status' => [
                'sometimes',
                'required',
                Rule::in('draft', 'issued', 'cancelled'),
                new ValidateInvoiceStatusTransition($currentStatus),
            ],
            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.description' => ['required_with:items', 'string', 'max:500'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1'],
            'items.*.unit_price' => ['required_with:items', 'numeric', 'min:0'],
            'date' => ['sometimes', 'nullable', 'date'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.in' => 'El estado debe ser "draft", "issued" o "cancelled".',
            'items.min' => 'El documento debe contener al menos 1 elemento.',
            'items.*.description.required_with' => 'La descripción es requerida para cada elemento.',
            'items.*.quantity.required_with' => 'La cantidad es requerida para cada elemento.',
            'items.*.unit_price.required_with' => 'El precio unitario es requerido para cada elemento.',
        ];
    }
}
