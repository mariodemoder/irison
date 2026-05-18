<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Products\StoreProductRequest;
use App\Http\Requests\Products\UpdateProductRequest;
use App\Models\Product;
use App\Services\Products\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class ProductController extends Controller
{
    public function __construct(private readonly ProductService $productService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Product::class);

        return response()->json($this->productService->index($request->all()));
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        Gate::authorize('create', Product::class);

        $clinicId = (int) Auth::user()->clinic_id;
        $result = $this->productService->store($request->validated(), $clinicId);

        return response()->json($result['payload'], $result['status']);
    }

    public function show(Product $product): JsonResponse
    {
        Gate::authorize('view', $product);

        return response()->json($this->productService->show($product));
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        Gate::authorize('update', $product);

        $clinicId = (int) Auth::user()->clinic_id;
        $result = $this->productService->update($product, $request->validated(), $clinicId);

        return response()->json($result['payload'], $result['status']);
    }
}
