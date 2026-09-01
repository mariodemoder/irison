<?php

namespace Modules\PatientPortal\Infrastructure\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\PatientPortal\Application\Services\PatientDashboardService;

class PatientDashboardController extends Controller
{
    public function __construct(
        private PatientDashboardService $dashboardService
    ) {}

    public function index(Request $request)
    {
        $data = $this->dashboardService->getForPatient($request->user());

        return response()->json($data);
    }
}
