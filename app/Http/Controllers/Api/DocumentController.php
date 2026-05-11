<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Bonus;
use App\Models\Document;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Product;
use App\Services\Documents\InvoicingService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
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
        $query = Document::query()->with(['patient:id,counter,first_name,last_name,nif']);

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

        $documentsCollection = collect($paginator->items());
        $appointmentIds = $documentsCollection
            ->filter(fn (Document $document) => $document->type === Document::TYPE_INVOICE && $document->type_from === 'appointment' && !empty($document->from_id))
            ->map(fn (Document $document) => (int) $document->from_id)
            ->unique()
            ->values();

        $packageIds = $documentsCollection
            ->filter(fn (Document $document) => $document->type === Document::TYPE_INVOICE && $document->type_from === 'package' && !empty($document->from_id))
            ->map(fn (Document $document) => (int) $document->from_id)
            ->unique()
            ->values();

        $appointmentPaymentStatuses = $appointmentIds->isEmpty()
            ? collect()
            : Appointment::query()
                ->whereIn('id', $appointmentIds)
                ->pluck('payment_status', 'id');

        $bonuses = $packageIds->isEmpty()
            ? collect()
            : Bonus::query()
                ->whereIn('id', $packageIds)
                ->get(['id', 'price'])
                ->keyBy('id');

        $packagePaymentRows = $packageIds->isEmpty()
            ? collect()
            : Payment::query()
                ->whereIn('package_id', $packageIds)
                ->where('concept', 'package')
                ->whereIn('status', ['completed', 'pending'])
                ->selectRaw("package_id, SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as completed_amount, SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as pending_amount")
                ->groupBy('package_id')
                ->get()
                ->keyBy('package_id');

        $packagePaymentStatuses = $packageIds->mapWithKeys(function (int $packageId) use ($bonuses, $packagePaymentRows) {
            $bonus = $bonuses->get($packageId);
            $price = (float) ($bonus?->price ?? 0);
            $row = $packagePaymentRows->get($packageId);
            $completedAmount = (float) ($row?->completed_amount ?? 0);
            $pendingAmount = (float) ($row?->pending_amount ?? 0);

            return [$packageId => $this->resolveBonusPaymentStatus($price, $completedAmount, $pendingAmount)];
        });

        $rows = $documentsCollection->map(function (Document $document) use ($appointmentPaymentStatuses, $packagePaymentStatuses) {
            $paymentStatus = $this->resolveDocumentPaymentStatus(
                $document,
                $appointmentPaymentStatuses,
                $packagePaymentStatuses
            );

            return [
                'id' => $document->id,
                'counter' => $document->counter,
                'type' => $document->type,
                'date' => $document->date,
                'amount' => (float) $document->amount,
                'status' => $document->status,
                'payment_status' => $paymentStatus,
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

        $document->load(['patient:id,counter,first_name,last_name,nif,email,phone,address,zip', 'items']);
        $originDocument = $this->resolveOriginDocument($document);
        $rectificationDocument = $this->resolveRectificationDocument($document);
        $paymentStatus = $this->resolveDocumentPaymentStatus($document);

        $items = $document->items->map(fn ($item) => [
            'id'           => $item->id,
            'type'         => $item->type,
            'reference_id' => $item->reference_id,
            'description'  => $item->description,
            'quantity'     => (float) $item->quantity,
            'unit_price'   => (float) $item->unit_price,
            'tax_rate'     => (float) $item->tax_rate,
            'buy_price'    => (float) $item->buy_price,
            'buy_tax'      => (float) $item->buy_tax,
            'total'        => (float) $item->total,
            'sort_order'   => $item->sort_order,
        ])->values()->all();

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
            'payment_status' => $paymentStatus,
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
            'items' => $items,
        ]);
    }

    public function storeVarios(Request $request): JsonResponse
    {
        Gate::authorize('create', Document::class);

        $user = $request->user();
        $clinicId = (int) ($user?->clinic_id ?? 0);

        try {
            $result = $this->invoicingService->issueVarios($request->all(), $user, $clinicId);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }

        $document = $result['document'];

        return response()->json([
            'message' => 'Factura creada correctamente.',
            'data' => [
                'id'      => $document->id,
                'counter' => $document->counter,
                'type'    => $document->type,
                'amount'  => (float) $document->amount,
            ],
        ], 201);
    }

    public function searchPatients(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Document::class);

        $q = trim((string) ($request->query('q') ?? ''));
        if (strlen($q) < 2) {
            return response()->json(['data' => []]);
        }

        $clinicId = (int) ($request->user()?->clinic_id ?? 0);
        $patients = Patient::query()
            ->where('clinic_id', $clinicId)
            ->where(function ($builder) use ($q) {
                $builder->where('first_name', 'like', "%{$q}%")
                    ->orWhere('last_name', 'like', "%{$q}%")
                    ->orWhere('nif', 'like', "%{$q}%");
            })
            ->limit(15)
            ->get(['id', 'counter', 'first_name', 'last_name', 'nif'])
            ->map(fn (Patient $p) => [
                'id'      => $p->id,
                'counter' => $p->counter,
                'name'    => $p->name,
                'nif'     => $p->nif,
            ]);

        return response()->json(['data' => $patients]);
    }

    public function searchAppointments(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Document::class);

        $patientId = (int) ($request->query('patient_id') ?? 0);
        if (!$patientId) {
            return response()->json(['data' => []]);
        }

        $clinicId = (int) ($request->user()?->clinic_id ?? 0);
        $appointments = Appointment::query()
            ->where('clinic_id', $clinicId)
            ->where('patient_id', $patientId)
            ->whereNull('invoice_id')
            ->orderByDesc('start_time')
            ->limit(50)
            ->get(['id', 'start_time', 'price', 'status', 'custom_type', 'app_type_id'])
            ->map(fn (Appointment $a) => [
                'id'          => $a->id,
                'start_time'  => $a->start_time,
                'price'       => (float) ($a->price ?? 0),
                'status'      => $a->status,
                'description' => $a->custom_type ?? ('Cita #' . $a->id),
            ]);

        return response()->json(['data' => $appointments]);
    }

    public function searchBonuses(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Document::class);

        $patientId = (int) ($request->query('patient_id') ?? 0);
        if (!$patientId) {
            return response()->json(['data' => []]);
        }

        $clinicId = (int) ($request->user()?->clinic_id ?? 0);
        $bonuses = Bonus::query()
            ->where('clinic_id', $clinicId)
            ->where('patient_id', $patientId)
            ->whereNull('invoice_id')
            ->orderByDesc('id')
            ->limit(50)
            ->get(['id', 'name', 'price', 'remaining_sessions', 'counter'])
            ->map(fn (Bonus $bonus) => [
                'id' => $bonus->id,
                'counter' => $bonus->counter,
                'name' => $bonus->name,
                'price' => (float) ($bonus->price ?? 0),
                'remaining_sessions' => (int) ($bonus->remaining_sessions ?? 0),
                'description' => trim(($bonus->counter ? $bonus->counter . ' · ' : '') . $bonus->name),
            ]);

        return response()->json(['data' => $bonuses]);
    }

    public function searchProducts(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Document::class);

        $q = trim((string) ($request->query('q') ?? ''));
        $clinicId = (int) ($request->user()?->clinic_id ?? 0);

        $query = Product::query()->where('clinic_id', $clinicId);
        if (strlen($q) >= 1) {
            $query->where(function ($b) use ($q) {
                $b->where('name', 'like', "%{$q}%")
                  ->orWhere('reference', 'like', "%{$q}%");
            });
        }

        $products = $query->limit(20)->get(['id', 'reference', 'name', 'sale_price', 'sale_tax', 'purchase_price', 'purchase_tax'])
            ->map(fn (Product $p) => [
                'id'            => $p->id,
                'reference'     => $p->reference,
                'name'          => $p->name,
                'sale_price'    => (float) $p->sale_price,
                'sale_tax'      => (float) $p->sale_tax,
                'purchase_price'=> (float) $p->purchase_price,
                'purchase_tax'  => (float) $p->purchase_tax,
            ]);

        return response()->json(['data' => $products]);
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

        $document->load([
            'patient:id,counter,first_name,last_name,nif,email,phone,address,zip',
            'items',
        ]);
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

    private function resolveDocumentPaymentStatus(
        Document $document,
        $appointmentPaymentStatuses = null,
        $packagePaymentStatuses = null
    ): ?string {
        if ($document->type !== Document::TYPE_INVOICE) {
            return $document->status;
        }

        if ($document->type_from === 'appointment' && !empty($document->from_id)) {
            $appointmentId = (int) $document->from_id;
            $resolved = $appointmentPaymentStatuses instanceof \Illuminate\Support\Collection
                ? $appointmentPaymentStatuses->get($appointmentId)
                : Appointment::query()->where('id', $appointmentId)->value('payment_status');

            return $resolved ? (string) $resolved : $document->status;
        }

        if ($document->type_from === 'package' && !empty($document->from_id)) {
            $packageId = (int) $document->from_id;

            if ($packagePaymentStatuses instanceof \Illuminate\Support\Collection && $packagePaymentStatuses->has($packageId)) {
                return (string) $packagePaymentStatuses->get($packageId);
            }

            $bonus = Bonus::query()->find($packageId, ['id', 'price']);
            if (!$bonus) {
                return $document->status;
            }

            $paymentTotals = Payment::query()
                ->where('package_id', $packageId)
                ->where('concept', 'package')
                ->whereIn('status', ['completed', 'pending'])
                ->selectRaw("SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as completed_amount, SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as pending_amount")
                ->first();

            return $this->resolveBonusPaymentStatus(
                (float) ($bonus->price ?? 0),
                (float) ($paymentTotals?->completed_amount ?? 0),
                (float) ($paymentTotals?->pending_amount ?? 0)
            );
        }

        return $document->status;
    }

    private function resolveBonusPaymentStatus(float $price, float $completedAmount, float $pendingAmount): string
    {
        if ($completedAmount <= 0 && $pendingAmount <= 0) {
            return 'pending';
        }

        if ($price > 0 && $completedAmount >= $price && $pendingAmount <= 0) {
            return 'paid';
        }

        return 'partially_paid';
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
