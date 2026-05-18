<?php

namespace App\Http\Requests\Products;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $productId = $this->route('product')?->id;
        $clinicId = (int) (auth()->user()->clinic_id ?? 0);

        return [
            'reference' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                Rule::unique('products', 'reference')
                    ->where(fn ($query) => $query->where('clinic_id', $clinicId))
                    ->ignore($productId),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'sale_price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'purchase_price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'sale_tax' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'purchase_tax' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'family' => ['sometimes', 'nullable', 'string', 'max:120'],
            'lot' => ['sometimes', 'nullable', 'string', 'max:120'],
        ];
    }

    public function messages(): array
    {
        return [
            'reference.unique' => 'Esta referencia ya existe en tu clínica.',
            'sale_tax.max' => 'El impuesto de venta no puede exceder 100.',
            'purchase_tax.max' => 'El impuesto de compra no puede exceder 100.',
        ];
    }
}
