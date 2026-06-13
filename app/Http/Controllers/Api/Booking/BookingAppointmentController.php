<?php

namespace App\Http\Controllers\Api\Booking;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingAppointmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Appointment::with(['patient', 'professional'])
            ->where('clinic_id', currentClinicId())
            ->where('booking_source', 'online');

        if ($request->date_from) {
            $query->whereDate('start_time', '>=', $request->date_from);
        }

        if ($request->date_to) {
            $query->whereDate('start_time', '<=', $request->date_to);
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        $appointments = $query->orderByDesc('start_time')->paginate(50);

        return response()->json($appointments);
    }
}
