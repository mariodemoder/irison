<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Spatie\Browsershot\Browsershot;

class DocumentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:issued,draft,cancelled'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 15);
        $query = Document::query()->with(['patient:id,first_name,last_name,nif']);

        if (!empty($validated['q'])) {
            $term = trim((string) $validated['q']);
            $query->where(function ($builder) use ($term) {
                $builder->where('patient_full_name', 'like', "%{$term}%")
                    ->orWhere('patient_nif', 'like', "%{$term}%")
                    ->orWhereHas('patient', function ($patientQuery) use ($term) {
                        $patientQuery->where('first_name', 'like', "%{$term}%")
                            ->orWhere('last_name', 'like', "%{$term}%")
                            ->orWhere('nif', 'like', "%{$term}%");
                    });
            });
        }

        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (!empty($validated['from_date'])) {
            $query->whereDate('date', '>=', $validated['from_date']);
        }

        if (!empty($validated['to_date'])) {
            $query->whereDate('date', '<=', $validated['to_date']);
        }

        $paginator = $query
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->appends($request->query());

        $rows = collect($paginator->items())->map(function (Document $document) {
            return [
                'id' => $document->id,
                'counter' => $document->counter,
                'date' => $document->date,
                'amount' => (float) $document->amount,
                'status' => $document->status,
                'typeinvoice' => $document->typeinvoice,
                'patient_nif' => $document->patient_nif,
                'patient_full_name' => $document->patient_full_name,
                'patient' => $document->patient ? [
                    'id' => $document->patient->id,
                    'name' => $document->patient->name,
                    'nif' => $document->patient->nif,
                ] : null,
                'created_at' => $document->created_at,
            ];
        })->values()->all();

        $summaryQuery = clone $query;

        return response()->json([
            'data' => $rows,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'summary' => [
                'count' => (int) $summaryQuery->count(),
                'total_amount' => (float) $summaryQuery->sum('amount'),
            ],
        ]);
    }

    public function show(Request $request, Document $document): JsonResponse
    {
        $this->ensureClinicAccessOr404($request, $document);

        $document->load(['patient:id,first_name,last_name,nif,email,phone,address,zip']);

        return response()->json([
            'id' => $document->id,
            'counter' => $document->counter,
            'type' => $document->type,
            'type_from' => $document->type_from,
            'typeinvoice' => $document->typeinvoice,
            'from_id' => $document->from_id,
            'clinic_name' => $document->clinic_name,
            'clinic_nif' => $document->clinic_nif,
            'clinic_address' => $document->clinic_address,
            'clinic_zip' => $document->clinic_zip,
            'clinic_province' => $document->clinic_province,
            'clinic_country' => $document->clinic_country,
            'user_full_name' => $document->user_full_name,
            'date' => $document->date,
            'amount' => (float) $document->amount,
            'status' => $document->status,
            'notes' => $document->notes,
            'patient_id' => $document->patient_id,
            'patient_nif' => $document->patient_nif,
            'patient_full_name' => $document->patient_full_name,
            'patient_email' => $document->patient_email,
            'patient_phone' => $document->patient_phone,
            'patient_address' => $document->patient_address,
            'patient_zip' => $document->patient_zip,
            'patient' => $document->patient ? [
                'id' => $document->patient->id,
                'name' => $document->patient->name,
                'nif' => $document->patient->nif,
                'email' => $document->patient->email,
                'phone' => $document->patient->phone,
                'address' => $document->patient->address,
                'zip' => $document->patient->zip,
            ] : null,
            'created_at' => $document->created_at,
        ]);
    }

    public function pdf(Request $request, Document $document): Response
    {
        $this->ensureClinicAccessOr404($request, $document);

        $document->load(['patient:id,first_name,last_name,nif,email,phone,address,zip']);

        $html = view('pdf.document-invoice', ['document' => $document])->render();

        $browsershot = Browsershot::html($html)
            ->format('A4')
            ->margins(10, 10, 10, 10)
            ->showBackground()
            ->setNodeModulePath(base_path('node_modules'));

        $pdfBinary = $browsershot->pdf();
        $filename = sprintf('factura-%s.pdf', $document->counter ?: $document->id);

        return response($pdfBinary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }

    private function ensureClinicAccessOr404(Request $request, Document $document): void
    {
        $user = $request->user();

        if (!$user || (int) $document->clinic_id !== (int) $user->clinic_id) {
            abort(404);
        }
    }
}
