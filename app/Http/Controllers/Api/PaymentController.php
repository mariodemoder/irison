<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Payments\PaymentService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $paymentService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->paymentService->index($request->all()));
    }

    public function appointmentOptions(Request $request): JsonResponse
    {
        $data = $request->validate([
            'patient_id' => 'required|integer|exists:patients,id',
            'current_appointment_id' => 'nullable|integer|exists:appointments,id',
        ]);

        $clinicId = (int) Auth::user()->clinic_id;

        $options = $this->paymentService->appointmentOptionsForPatient(
            (int) $data['patient_id'],
            $clinicId,
            isset($data['current_appointment_id']) ? (int) $data['current_appointment_id'] : null
        );

        return response()->json(['data' => $options]);
    }

    public function packageOptions(Request $request): JsonResponse
    {
        $data = $request->validate([
            'patient_id' => 'required|integer|exists:patients,id',
            'current_package_id' => 'nullable|integer|exists:bonuses,id',
            'only_unpaid' => 'nullable|boolean',
        ]);

        $clinicId = (int) Auth::user()->clinic_id;
        $onlyUnpaid = array_key_exists('only_unpaid', $data) ? (bool) $data['only_unpaid'] : true;

        $options = $this->paymentService->packageOptionsForPatient(
            (int) $data['patient_id'],
            $clinicId,
            isset($data['current_package_id']) ? (int) $data['current_package_id'] : null,
            $onlyUnpaid
        );
        
        return response()->json(['data' => $options]);
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $clinicId = (int) Auth::user()->clinic_id;
            $result = $this->paymentService->store($request->all(), $clinicId);

            return response()->json($result['payload'], $result['status']);
        } catch (DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function show(Payment $payment): JsonResponse
    {
        return response()->json($this->paymentService->show($payment));
    }

    public function update(Request $request, Payment $payment): JsonResponse
    {
        try {
            $clinicId = (int) Auth::user()->clinic_id;
            $result = $this->paymentService->update($payment, $request->all(), $clinicId);

            return response()->json($result['payload'], $result['status']);
        } catch (DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }
}
