<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bonus;
use App\Models\Document;
use App\Services\Documents\InvoicingService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Spatie\Browsershot\Browsershot;

class DocumentController extends Controller
{
    public function __construct(private readonly InvoicingService $invoicingService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Document::class);

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
                'type' => $document->type,
                'date' => $document->date,
                'amount' => (float) $document->amount,
                'status' => $document->status,
                'typeinvoice' => $document->typeinvoice,
                'patient_nif' => $document->patient_nif,
                'patient_full_name' => $document->patient_full_name,
                'patient' => $document->patient ? [
                    'id' => $document->patient->id,
                    'counter' => $document->patient->counter,
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
        Gate::authorize('view', $document);

        $document->load(['patient:id,first_name,last_name,nif,email,phone,address,zip']);
        $originDocument = $this->resolveOriginDocument($document);
        $rectificationDocument = $this->resolveRectificationDocument($document);

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
                'counter' => $document->patient->counter,
                'name' => $document->patient->name,
                'nif' => $document->patient->nif,
                'email' => $document->patient->email,
                'phone' => $document->patient->phone,
                'address' => $document->patient->address,
                'zip' => $document->patient->zip,
            ] : null,
            'origin_document' => $originDocument ? [
                'id' => $originDocument->id,
                'counter' => $originDocument->counter,
            ] : null,
            'rectification_document' => $rectificationDocument ? [
                'id' => $rectificationDocument->id,
                'counter' => $rectificationDocument->counter,
            ] : null,
            'pdf_url' => url('/api/documents/' . $document->id . '/pdf'),
            'created_at' => $document->created_at,
        ]);
    }

    public function issueAbono(Request $request, Document $document): JsonResponse
    {
        Gate::authorize('view', $document);

        try {
            $result = $this->invoicingService->issueAbonoForInvoice($document);
        } catch (DomainException $exception) {
            return response()->json(['error' => $exception->getMessage()], 422);
        }

        $abono = $result['document'];
        $created = (bool) $result['created'];

        return response()->json([
            'message' => $created ? 'Factura rectificativa emitida correctamente.' : 'La factura ya tiene un abono emitido.',
            'data' => [
                'id' => $abono->id,
                'counter' => $abono->counter,
                'type' => $abono->type,
            ],
        ], $created ? 201 : 200);
    }

    public function pdf(Request $request, Document $document): Response
    {
        Gate::authorize('view', $document);

        $document->load(['patient:id,first_name,last_name,nif,email,phone,address,zip']);
        $clinic = $request->user()?->clinic;
        $originDocument = $this->resolveOriginDocument($document);

        $background = $this->buildInvoiceBackgroundPayload(
            $clinic?->invoice_background_path
        );

        $html = view('pdf.document-invoice', [
            'document' => $document,
            'originDocument' => $originDocument,
            'invoiceBackgroundDataUri' => $background['data_uri'],
            'bonus' => $this->resolveBonusForPdf($document, $originDocument),
        ])->render();

        $browsershot = Browsershot::html($html)
            ->format('A4')
            ->margins($background['is_a4'] ? 0 : 10, $background['is_a4'] ? 0 : 10, $background['is_a4'] ? 0 : 10, $background['is_a4'] ? 0 : 10)
            ->showBackground()
            ->setNodeModulePath(base_path('node_modules'));

        $pdfBinary = $browsershot->pdf();
        $filename = sprintf(
            '%s-%s.pdf',
            $document->type === Document::TYPE_ABONO ? 'factura-rectificativa' : 'factura',
            $document->counter ?: $document->id
        );

        return response($pdfBinary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }

    private function resolveOriginDocument(Document $document): ?Document
    {
        if ($document->type_from !== Document::TYPE_INVOICE || empty($document->from_id)) {
            return null;
        }

        return Document::query()
            ->select(['id', 'counter', 'type', 'type_from', 'from_id', 'typeinvoice'])
            ->find((int) $document->from_id);
    }

    private function resolveRectificationDocument(Document $document): ?Document
    {
        if ($document->type !== Document::TYPE_INVOICE) {
            return null;
        }

        return Document::query()
            ->select(['id', 'counter'])
            ->where('clinic_id', (int) $document->clinic_id)
            ->where('type', Document::TYPE_ABONO)
            ->where('type_from', Document::TYPE_INVOICE)
            ->where('from_id', (int) $document->id)
            ->latest('id')
            ->first();
    }

    private function resolveBonusForPdf(Document $document, ?Document $originDocument): ?Bonus
    {
        if (!in_array($document->typeinvoice, ['package', 'bonus', 'bono', 'pack'], true)) {
            return null;
        }

        $bonusId = null;

        if ($document->type_from === 'package' && !empty($document->from_id)) {
            $bonusId = (int) $document->from_id;
        }

        if (
            $bonusId === null
            && $document->type_from === Document::TYPE_INVOICE
            && $originDocument?->type_from === 'package'
            && !empty($originDocument->from_id)
        ) {
            $bonusId = (int) $originDocument->from_id;
        }

        return $bonusId ? Bonus::query()->find($bonusId) : null;
    }

    private function buildInvoiceBackgroundPayload(?string $path): array
    {
        if (empty($path) || !Storage::disk('public')->exists($path)) {
            return [
                'data_uri' => null,
                'is_a4' => false,
            ];
        }

        $content = Storage::disk('public')->get($path);
        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));

        $mime = match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'application/octet-stream',
        };

        return [
            'data_uri' => 'data:' . $mime . ';base64,' . base64_encode($content),
            'is_a4' => $this->isA4LikeImage($content),
        ];
    }

    private function isA4LikeImage(string $binary): bool
    {
        $size = @getimagesizefromstring($binary);
        if ($size === false || empty($size[0]) || empty($size[1])) {
            return false;
        }

        $width = (float) $size[0];
        $height = (float) $size[1];
        if ($width <= 0 || $height <= 0) {
            return false;
        }

        $ratio = max($width, $height) / min($width, $height);
        $a4Ratio = 1.41421356;

        return abs($ratio - $a4Ratio) <= 0.03;
    }
}
