<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConsentCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class ConsentCategoryController extends Controller
{
    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', ConsentCategory::class);

        $categories = ConsentCategory::query()
            ->where('clinic_id', Auth::user()->clinic_id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $categories]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', ConsentCategory::class);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $category = ConsentCategory::create([
            'clinic_id' => Auth::user()->clinic_id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        return response()->json(['data' => $category], 201);
    }

    public function update(Request $request, ConsentCategory $consentCategory): JsonResponse
    {
        Gate::authorize('update', $consentCategory);

        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $consentCategory->update($data);

        return response()->json(['data' => $consentCategory]);
    }

    public function destroy(ConsentCategory $consentCategory): JsonResponse
    {
        Gate::authorize('delete', $consentCategory);

        $consentCategory->delete();

        return response()->json(['message' => 'Categoría eliminada']);
    }
}
