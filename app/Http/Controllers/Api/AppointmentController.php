<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Appointments\StoreAppointmentRequest;
use App\Http\Requests\Appointments\UpdateAppointmentRequest;
use App\Models\Appointment;
use App\Models\Bonus;
use App\Models\CreditUsage;
use App\Models\Patient;
use App\Models\Payment;
use Illuminate\Http\Request;
use App\Services\Appointments\AppointmentService;
use App\Support\ActivityLogger;
use App\Services\Validation\RequestValidationOrchestrator;
use App\Services\Documents\InvoicingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class AppointmentController extends Controller
{
    protected AppointmentService $appointmentService;
    protected RequestValidationOrchestrator $requestValidationOrchestrator;
    protected InvoicingService $invoicingService;

    public function __construct(
        AppointmentService $appointmentService,
        RequestValidationOrchestrator $requestValidationOrchestrator,
        InvoicingService $invoicingService
    )
    {
        $this->appointmentService = $appointmentService;
        $this->requestValidationOrchestrator = $requestValidationOrchestrator;
        $this->invoicingService = $invoicingService;
    }

    public function index(Request $request)
    {
        Gate::authorize('viewAny', Appointment::class);

        return $this->appointmentService->list($request->all());
    }

    public function store(StoreAppointmentRequest $request)
    {
        Gate::authorize('create', Appointment::class);

        try {
            $appointment = $this->appointmentService->create($request->validated());
            return response()->json($appointment, 201);
        } catch (\DomainException $e) {
            $status = $this->resolveDomainExceptionStatus($e->getMessage());
            return response()->json(['error' => $e->getMessage()], $status);
        }
    }

    public function show(Request $request, Appointment $appointment)
    {
        Gate::authorize('view', $appointment);

        return $this->appointmentService->show($appointment, $request->all());
    }

    public function formBootstrap(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Appointment::class);

        $clinicId = (int) (currentClinicId() ?? 0);
        $appointmentId = (int) $request->query('appointment_id', 0);
        $patientId = (int) $request->query('patient_id', 0);
        $patientsPerPage = (int) $request->query('patients_per_page', 200);

        $patients = Patient::query()
            ->where('clinic_id', $clinicId)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit($patientsPerPage > 0 ? $patientsPerPage : 200)
            ->get(['id', 'first_name', 'last_name', 'nif', 'phone', 'email', 'address', 'zip', 'province', 'country', 'birth_date', 'notes', 'clinic_id', 'created_at', 'updated_at']);

        $patientsPayload = $patients->map(function (Patient $patient) {
            return [
                'id' => $patient->id,
                'clinic_id' => $patient->clinic_id,
                'counter' => $patient->counter,
                'nif' => $patient->nif,
                'name' => $patient->name,
                'phone' => $patient->phone,
                'email' => $patient->email,
                'address' => $patient->address,
                'zip' => $patient->zip,
                'province' => $patient->province,
                'country' => $patient->country,
                'birth_date' => $patient->birth_date,
                'notes' => $patient->notes,
                'available_credit' => $patient->availableCredit(),
                'created_at' => $patient->created_at,
                'updated_at' => $patient->updated_at,
            ];
        })->values();

        $appointmentPayload = null;
        if ($appointmentId > 0) {
            $appointment = Appointment::query()
                ->where('clinic_id', $clinicId)
                ->findOrFail($appointmentId);

            Gate::authorize('view', $appointment);
            $appointmentPayload = $this->appointmentService->show($appointment, []);
            $patientId = (int) ($appointmentPayload->patient_id ?? $appointment->patient_id ?? $patientId);
        }

        $bonuses = collect();
        $pendingCreditPayments = collect();

        if ($patientId > 0) {
            $bonuses = Bonus::query()
                ->where('clinic_id', $clinicId)
                ->where('patient_id', $patientId)
                ->orderByDesc('id')
                ->get(['id', 'patient_id', 'name', 'total_sessions', 'remaining_sessions', 'price', 'invoice_id', 'expires_at']);

            $pendingCreditPayments = Payment::query()
                ->where('clinic_id', $clinicId)
                ->where('patient_id', $patientId)
                ->where('concept', 'credit')
                ->where('status', 'pending')
                ->orderByDesc('created_at')
                ->limit(100)
                ->get()
                ->map(function (Payment $payment) {
                    $creditUsedAmount = (float) CreditUsage::query()
                        ->whereNull('reversed_at')
                        ->where('payment_id', $payment->id)
                        ->where('patient_id', $payment->patient_id)
                        ->sum('amount');

                    $creditPendingAmount = max(((float) $payment->amount) - $creditUsedAmount, 0);

                    return [
                        'id' => $payment->id,
                        'counter' => $payment->counter,
                        'patient_id' => $payment->patient_id,
                        'concept' => $payment->concept,
                        'appointment_id' => $payment->appointment_id,
                        'package_id' => $payment->package_id,
                        'amount' => (float) $payment->amount,
                        'credit_used_amount' => $creditUsedAmount,
                        'credit_pending_amount' => $creditPendingAmount,
                        'method' => $payment->method,
                        'status' => $payment->status,
                        'notes' => $payment->notes,
                        'paid_at' => $payment->paid_at,
                        'created_at' => $payment->created_at,
                        'updated_at' => $payment->updated_at,
                    ];
                })
                ->values();
        }

        // Cargar tipos de citas
        $appointmentTypes = \App\Models\AppointmentType::query()
            ->where('clinic_id', $clinicId)
            ->withCount('bonusTypes')
            ->orderBy('description')
            ->get(['id', 'description', 'estimated_hours', 'estimated_minutes', 'price'])
            ->map(static function ($item) {
                return [
                    'id' => (int) $item->id,
                    'description' => $item->description,
                    'estimated_hours' => (int) $item->estimated_hours,
                    'estimated_minutes' => (int) $item->estimated_minutes,
                    'price' => (float) $item->price,
                    'payment_type' => ((int) ($item->bonus_types_count ?? 0)) > 0 ? 'abono' : 'simple',
                ];
            })
            ->values()
            ->toArray();

        return response()->json([
            'data' => [
                'patients' => $patientsPayload,
                'appointment' => $appointmentPayload,
                'bonuses' => $bonuses->values(),
                'pending_credit_payments' => $pendingCreditPayments,
                'appointment_types' => $appointmentTypes,
            ],
        ]);
    }

    public function update(UpdateAppointmentRequest $request, Appointment $appointment)
    {
        Gate::authorize('update', $appointment);

        try {
            return $this->appointmentService->update($appointment, $request->validated());
        } catch (\DomainException $e) {
            $status = $this->resolveDomainExceptionStatus($e->getMessage());
            return response()->json(['error' => $e->getMessage()], $status);
        }
    }

    public function cancel(Appointment $appointment)
    {
        Gate::authorize('update', $appointment);

        try {
            return $this->appointmentService->cancel($appointment);
        } catch (\DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    private function resolveDomainExceptionStatus(string $message): int
    {
        $normalized = mb_strtolower(trim($message));

        if (str_contains($normalized, 'hora de inicio') && str_contains($normalized, 'hora de fin')) {
            return 400;
        }

        return 422;
    }

    public function destroy(Appointment $appointment)
    {
        Gate::authorize('delete', $appointment);

        $this->appointmentService->delete($appointment);
        return response()->noContent();
    }

    public function issueInvoice(Request $request, Appointment $appointment): JsonResponse
    {
        Gate::authorize('issueInvoice', $appointment);

        $validated = $request->validate([
            'notes' => 'nullable|string',
        ]);

        $user = $request->user();
        $notes = isset($validated['notes']) ? (string) $validated['notes'] : null;

        $result = $this->invoicingService->issueForAppointment($appointment, $user, $notes);
        $document = $result['document'];
        $created = (bool) $result['created'];

        if ($created) {
            ActivityLogger::log(
                tenantId: (int) ($user?->clinic_id ?? 0),
                userId: (int) ($user?->id ?? 0),
                event: 'document_created',
                description: 'Documento creado desde una cita',
                metadata: [
                    'document_id' => (int) $document->id,
                    'appointment_id' => (int) $appointment->id,
                    'source' => 'appointments.issue_invoice',
                ],
                ip: $request->ip(),
            );
        }

        return response()->json([
            'message' => $created ? 'Factura emitida correctamente.' : 'La cita ya tenía una factura emitida.',
            'data' => [
                'id' => $document->id,
                'counter' => $document->counter,
            ],
        ], $created ? 201 : 200);
    }
}