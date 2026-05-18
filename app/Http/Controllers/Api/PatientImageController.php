<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PatientImages\StoreBatchPatientImageRequest;
use App\Http\Requests\PatientImages\UpdatePatientImageRequest;
use App\Models\Patient;
use App\Models\PatientImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class PatientImageController extends Controller
{
    private const MAX_FILES_PER_PATIENT = 6;

    private const MAX_FILE_SIZE_KB = 200;

    public function index(Patient $patient): JsonResponse
    {
        Gate::authorize('view', $patient);

        $rows = PatientImage::query()
            ->where('clinic_id', (int) $patient->clinic_id)
            ->where('patient_id', (int) $patient->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (PatientImage $image) => $this->mapImage($image))
            ->values()
            ->all();

        return response()->json(['data' => $rows]);
    }

    public function storeBatch(StoreBatchPatientImageRequest $request, Patient $patient): JsonResponse
    {
        Gate::authorize('update', $patient);

        $existingCount = PatientImage::query()
            ->where('clinic_id', (int) $patient->clinic_id)
            ->where('patient_id', (int) $patient->id)
            ->count();

        $validated = $request->validated();

        if ($existingCount + count($validated['items']) > self::MAX_FILES_PER_PATIENT) {
            return response()->json([
                'message' => 'Solo se permiten hasta 6 archivos por paciente.',
            ], 422);
        }

        $rows = [];

        foreach ($validated['items'] as $item) {
            $file = $item['file'];
            $path = $file->store("patients/{$patient->id}/images", 'public');

            $image = PatientImage::create([
                'clinic_id' => (int) $patient->clinic_id,
                'patient_id' => (int) $patient->id,
                'description' => (string) $item['description'],
                'path' => $path,
                'mime_type' => $file->getMimeType(),
                'size_bytes' => (int) $file->getSize(),
            ]);

            $rows[] = $this->mapImage($image);
        }

        return response()->json([
            'message' => 'Archivos adjuntados correctamente.',
            'data' => $rows,
        ], 201);
    }

    public function update(UpdatePatientImageRequest $request, Patient $patient, PatientImage $image): JsonResponse
    {
        Gate::authorize('update', $patient);

        if ((int) $image->patient_id !== (int) $patient->id || (int) $image->clinic_id !== (int) $patient->clinic_id) {
            return response()->json(['message' => 'Imagen no encontrada para este paciente.'], 404);
        }

        $validated = $request->validated();

        $payload = [
            'description' => (string) $validated['description'],
        ];

        if (!empty($validated['file'])) {
            if (!empty($image->path) && Storage::disk('public')->exists($image->path)) {
                Storage::disk('public')->delete($image->path);
            }

            $file = $validated['file'];
            $newPath = $file->store("patients/{$patient->id}/images", 'public');

            $payload['path'] = $newPath;
            $payload['mime_type'] = $file->getMimeType();
            $payload['size_bytes'] = (int) $file->getSize();
        }

        $image->update($payload);

        return response()->json([
            'message' => 'Archivo actualizado correctamente.',
            'data' => $this->mapImage($image->fresh()),
        ]);
    }

    public function destroy(Patient $patient, PatientImage $image): JsonResponse
    {
        Gate::authorize('update', $patient);

        if ((int) $image->patient_id !== (int) $patient->id || (int) $image->clinic_id !== (int) $patient->clinic_id) {
            return response()->json(['message' => 'Imagen no encontrada para este paciente.'], 404);
        }

        if (!empty($image->path) && Storage::disk('public')->exists($image->path)) {
            Storage::disk('public')->delete($image->path);
        }

        $image->delete();

        return response()->json([
            'message' => 'Imagen eliminada correctamente.',
        ]);
    }

    private function mapImage(PatientImage $image): array
    {
        return [
            'id' => $image->id,
            'description' => $image->description,
            'path' => $image->path,
            'url' => asset('storage/' . ltrim((string) $image->path, '/')),
            'mime_type' => $image->mime_type,
            'size_bytes' => (int) $image->size_bytes,
            'created_at' => $image->created_at,
            'updated_at' => $image->updated_at,
        ];
    }
}
