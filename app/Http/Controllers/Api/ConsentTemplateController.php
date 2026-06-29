<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConsentTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class ConsentTemplateController extends Controller
{
    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', ConsentTemplate::class);

        $templates = ConsentTemplate::query()
            ->where('clinic_id', Auth::user()->clinic_id)
            ->with('category:id,name')
            ->orderBy('title')
            ->get();

        return response()->json(['data' => $templates]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', ConsentTemplate::class);

        $data = $request->validate([
            'category_id' => 'nullable|integer|exists:consent_categories,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'content' => 'required|string',
            'status' => 'nullable|in:active,inactive',
        ]);

        $template = ConsentTemplate::create([
            'clinic_id' => Auth::user()->clinic_id,
            'category_id' => $data['category_id'] ?? null,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'content' => $data['content'],
            'version' => 1,
            'status' => $data['status'] ?? 'active',
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        return response()->json(['data' => $template->load('category:id,name')], 201);
    }

    public function show(ConsentTemplate $consentTemplate): JsonResponse
    {
        Gate::authorize('view', $consentTemplate);

        return response()->json(['data' => $consentTemplate->load('category:id,name')]);
    }

    public function update(Request $request, ConsentTemplate $consentTemplate): JsonResponse
    {
        Gate::authorize('update', $consentTemplate);

        $data = $request->validate([
            'category_id' => 'nullable|integer|exists:consent_categories,id',
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'content' => 'sometimes|required|string',
            'status' => 'nullable|in:active,inactive',
        ]);

        $oldContent = $consentTemplate->content;
        $newContent = $data['content'] ?? $oldContent;

        $consentTemplate->update([
            'category_id' => $data['category_id'] ?? $consentTemplate->category_id,
            'title' => $data['title'] ?? $consentTemplate->title,
            'description' => array_key_exists('description', $data) ? $data['description'] : $consentTemplate->description,
            'content' => $newContent,
            'version' => $newContent !== $oldContent ? $consentTemplate->version + 1 : $consentTemplate->version,
            'status' => $data['status'] ?? $consentTemplate->status,
            'updated_by' => Auth::id(),
        ]);

        return response()->json(['data' => $consentTemplate->load('category:id,name')]);
    }

    public function destroy(ConsentTemplate $consentTemplate): JsonResponse
    {
        Gate::authorize('delete', $consentTemplate);

        $consentTemplate->delete();

        return response()->json(['message' => 'Plantilla eliminada']);
    }

    public function versions(ConsentTemplate $consentTemplate): JsonResponse
    {
        Gate::authorize('view', $consentTemplate);

        return response()->json(['data' => [
            'current_version' => $consentTemplate->version,
            'versions' => range(1, $consentTemplate->version),
        ]]);
    }
}
