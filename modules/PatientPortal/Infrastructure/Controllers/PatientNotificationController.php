<?php

namespace Modules\PatientPortal\Infrastructure\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\PatientPortal\Application\Services\PatientNotificationService;

class PatientNotificationController extends Controller
{
    public function __construct(
        private PatientNotificationService $notificationService
    ) {}

    public function index(Request $request)
    {
        $notifications = $this->notificationService->index($request->user());

        return response()->json($notifications);
    }

    public function markRead(Request $request, int $id)
    {
        $notification = \App\Models\PatientPortalNotification::where('clinic_id', $request->user()->clinic_id)
            ->where('patient_id', $request->user()->id)
            ->where('id', $id)
            ->firstOrFail();

        $this->notificationService->markRead($request->user(), $notification);

        return response()->json(['message' => 'Notificación marcada como leída.']);
    }
}
