<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use Illuminate\Http\Request;
use App\Services\Appointments\AppointmentService;
use App\Services\Documents\InvoicingService;
use Illuminate\Http\JsonResponse;

class AppointmentController extends Controller
{
    protected AppointmentService $appointmentService;
    protected InvoicingService $invoicingService;

    public function __construct(AppointmentService $appointmentService, InvoicingService $invoicingService)
    {
        $this->appointmentService = $appointmentService;
        $this->invoicingService = $invoicingService;
    }

    public function index(Request $request)
    {
        return $this->appointmentService->list($request->all());
    }

    public function store(Request $request)
    {
        $data = $request->all();

        try {
            $appointment = $this->appointmentService->create($data);
            return response()->json($appointment, 201);
        } catch (\DomainException $e) {
            $status = $this->resolveDomainExceptionStatus($e->getMessage());
            return response()->json(['error' => $e->getMessage()], $status);
        }
    }

    public function show(Request $request, Appointment $appointment)
    {
        return $this->appointmentService->show($appointment, $request->all());
    }

    public function update(Request $request, Appointment $appointment)
    {
        $data = $request->all();

        try {
            return $this->appointmentService->update($appointment, $data);
        } catch (\DomainException $e) {
            $status = $this->resolveDomainExceptionStatus($e->getMessage());
            return response()->json(['error' => $e->getMessage()], $status);
        }
    }

    public function cancel(Appointment $appointment)
    {
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
        $this->appointmentService->delete($appointment);
        return response()->noContent();
    }

    public function issueInvoice(Request $request, Appointment $appointment): JsonResponse
    {
        $user = $request->user();

        if (!$user || (int) $user->clinic_id !== (int) $appointment->clinic_id) {
            return response()->json([
                'message' => 'No autorizado para emitir factura en esta cita.',
            ], 403);
        }

        $result = $this->invoicingService->issueForAppointment($appointment, $user);
        $document = $result['document'];
        $created = (bool) $result['created'];

        return response()->json([
            'message' => $created ? 'Factura emitida correctamente.' : 'La cita ya tenía una factura emitida.',
            'data' => [
                'id' => $document->id,
                'counter' => $document->counter,
            ],
        ], $created ? 201 : 200);
    }
}