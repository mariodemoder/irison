<?php

namespace App\Http\Requests\Products;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $clinicId = (int) (auth()->user()->clinic_id ?? 0);

        return [
            'reference' => [
                'required',
                'string',
                'max:100',
                Rule::unique('products', 'reference')->where(fn ($query) => $query->where('clinic_id', $clinicId)),
            ],
            'name' => ['required', 'string', 'max:255'],
            'sale_price' => ['required', 'numeric', 'min:0'],
            'purchase_price' => ['required', 'numeric', 'min:0'],
            'sale_tax' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'purchase_tax' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'family' => ['nullable', 'string', 'max:120'],
            'lot' => ['nullable', 'string', 'max:120'],
        ];
    }

    public function messages(): array
    {
        return [
            'reference.required' => 'La referencia del producto es requerida.',
            'reference.unique' => 'Esta referencia ya existe en tu clínica.',
            'name.required' => 'El nombre del producto es requerido.',
            'sale_price.required' => 'El precio de venta es requerido.',
            'sale_price.numeric' => 'El precio de venta debe ser un número.',
            'sale_price.min' => 'El precio de venta no puede ser negativo.',
            'purchase_price.required' => 'El precio de compra es requerido.',
            'sale_tax.max' => 'El impuesto de venta no puede exceder 100.',
            'purchase_tax.max' => 'El impuesto de compra no puede exceder 100.',
        ];
    }
}
