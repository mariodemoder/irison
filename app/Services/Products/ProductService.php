<?php

namespace App\Services\Products;

use App\Models\Product;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ProductService
{
    public function index(array $filters): array
    {
        $perPage = (int) ($filters['per_page'] ?? 15);
        $q = strtolower(trim((string) ($filters['q'] ?? '')));

        $query = Product::query()->orderBy('name');

        if ($q !== '') {
            $like = '%' . $q . '%';
            $query->where(function ($sub) use ($like) {
                $sub->whereRaw('LOWER(reference) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(family) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(lot) LIKE ?', [$like]);
            });
        }

        $paginator = $query->paginate($perPage);

        return [
            'data' => $this->mapPaginatorItems($paginator),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    public function store(array $input, int $clinicId): array
    {
        $data = Validator::make($input, [
            'reference' => [
                'required',
                'string',
                'max:100',
                Rule::unique('products', 'reference')->where(fn ($query) => $query->where('clinic_id', $clinicId)),
            ],
            'name' => 'required|string|max:255',
            'sale_price' => 'required|numeric|min:0',
            'purchase_price' => 'required|numeric|min:0',
            'sale_tax' => 'nullable|numeric|min:0|max:100',
            'purchase_tax' => 'nullable|numeric|min:0|max:100',
            'family' => 'nullable|string|max:120',
            'lot' => 'nullable|string|max:120',
        ])->validate();

        $product = Product::create([
            'clinic_id' => $clinicId,
            'reference' => trim((string) $data['reference']),
            'name' => trim((string) $data['name']),
            'sale_price' => $data['sale_price'],
            'purchase_price' => $data['purchase_price'],
            'sale_tax' => $data['sale_tax'] ?? 0,
            'purchase_tax' => $data['purchase_tax'] ?? 0,
            'family' => $data['family'] ?? null,
            'lot' => $data['lot'] ?? null,
        ]);

        return [
            'status' => 201,
            'payload' => $this->mapProduct($product),
        ];
    }

    public function show(Product $product): array
    {
        return $this->mapProduct($product);
    }

    public function update(Product $product, array $input, int $clinicId): array
    {
        $data = Validator::make($input, [
            'reference' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                Rule::unique('products', 'reference')
                    ->ignore($product->id)
                    ->where(fn ($query) => $query->where('clinic_id', $clinicId)),
            ],
            'name' => 'sometimes|required|string|max:255',
            'sale_price' => 'sometimes|required|numeric|min:0',
            'purchase_price' => 'sometimes|required|numeric|min:0',
            'sale_tax' => 'nullable|numeric|min:0|max:100',
            'purchase_tax' => 'nullable|numeric|min:0|max:100',
            'family' => 'nullable|string|max:120',
            'lot' => 'nullable|string|max:120',
        ])->validate();

        $payload = [];

        foreach (['reference', 'name', 'sale_price', 'purchase_price', 'sale_tax', 'purchase_tax', 'family', 'lot'] as $field) {
            if (array_key_exists($field, $data)) {
                $payload[$field] = in_array($field, ['reference', 'name'], true)
                    ? trim((string) $data[$field])
                    : $data[$field];
            }
        }

        $product->update($payload);

        return [
            'status' => 200,
            'payload' => $this->mapProduct($product),
        ];
    }

    private function mapPaginatorItems(LengthAwarePaginator $paginator): array
    {
        return $paginator->getCollection()->transform(function (Product $product) {
            return $this->mapProduct($product);
        })->toArray();
    }

    private function mapProduct(Product $product): array
    {
        return [
            'id' => $product->id,
            'clinic_id' => $product->clinic_id,
            'reference' => $product->reference,
            'name' => $product->name,
            'sale_price' => (float) $product->sale_price,
            'purchase_price' => (float) $product->purchase_price,
            'sale_tax' => (float) $product->sale_tax,
            'purchase_tax' => (float) $product->purchase_tax,
            'family' => $product->family,
            'lot' => $product->lot,
            'created_at' => $product->created_at,
            'updated_at' => $product->updated_at,
        ];
    }
}
