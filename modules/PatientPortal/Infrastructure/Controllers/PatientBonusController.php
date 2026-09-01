<?php

namespace Modules\PatientPortal\Infrastructure\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\PatientPortal\Application\Services\PatientBonusService;

class PatientBonusController extends Controller
{
    public function __construct(
        private PatientBonusService $bonusService
    ) {}

    public function index(Request $request)
    {
        $bonuses = $this->bonusService->index($request->user());

        return response()->json(['bonuses' => $bonuses]);
    }

    public function show(Request $request, int $id)
    {
        $bonus = $this->bonusService->show($request->user(), $id);

        return response()->json(['bonus' => $bonus]);
    }
}
