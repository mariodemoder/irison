<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reminder;
use App\Services\Reminders\ReminderService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ReminderController extends Controller
{
    public function __construct(private readonly ReminderService $reminderService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Reminder::class);

        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:sent,failed'],
            'reminder_type' => ['nullable', 'in:24h,2h'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json($this->reminderService->index($validated));
    }

    public function show(Reminder $reminder): JsonResponse
    {
        Gate::authorize('view', $reminder);

        return response()->json($this->reminderService->show($reminder));
    }

    public function resend(Reminder $reminder): JsonResponse
    {
        Gate::authorize('update', $reminder);

        try {
            $newReminder = $this->reminderService->resend($reminder);
        } catch (DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Recordatorio reenviado correctamente.',
            'data' => [
                'id' => $newReminder->id,
                'status' => $newReminder->status,
                'sent_at' => $newReminder->sent_at,
            ],
        ]);
    }
}
