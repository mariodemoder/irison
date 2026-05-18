<?php

namespace App\Http\Requests\Documents;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Autorización manejada por Policy
    }

    /**
     * Reglas de validación para crear un documento (factura).
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in('draft', 'issued')],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:500'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'El estado del documento es requerido.',
            'status.in' => 'El estado debe ser "draft" o "issued".',
            'items.required' => 'Debe incluir al menos un elemento en el documento.',
            'items.min' => 'El documento debe contener al menos 1 elemento.',
            'items.*.description.required' => 'La descripción del elemento es requerida.',
            'items.*.quantity.required' => 'La cantidad es requerida.',
            'items.*.quantity.min' => 'La cantidad debe ser al menos 1.',
            'items.*.unit_price.required' => 'El precio unitario es requerido.',
            'items.*.unit_price.min' => 'El precio unitario no puede ser negativo.',
            'date.date' => 'La fecha debe ser una fecha válida.',
        ];
    }
}
