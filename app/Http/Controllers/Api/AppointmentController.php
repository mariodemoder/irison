<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use Illuminate\Http\Request;
use App\Services\Appointments\AppointmentService;

class AppointmentController extends Controller
{
    protected AppointmentService $appointmentService;

    public function __construct(AppointmentService $appointmentService)
    {
        $this->appointmentService = $appointmentService;
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
            return response()->json(['error' => $e->getMessage()], 422);
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
            return response()->json(['error' => $e->getMessage()], 422);
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

    public function destroy(Appointment $appointment)
    {
        $this->appointmentService->delete($appointment);
        return response()->noContent();
    }
}