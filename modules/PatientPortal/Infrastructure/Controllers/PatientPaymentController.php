<?php

namespace Modules\PatientPortal\Infrastructure\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\PatientPortal\Application\Services\PatientPaymentService;

class PatientPaymentController extends Controller
{
    public function __construct(
        private PatientPaymentService $paymentService
    ) {}

    public function index(Request $request)
    {
        $payments = $this->paymentService->index($request->user());

        return response()->json($payments);
    }

    public function pending(Request $request)
    {
        $payments = $this->paymentService->pending($request->user());

        return response()->json(['payments' => $payments]);
    }
}
