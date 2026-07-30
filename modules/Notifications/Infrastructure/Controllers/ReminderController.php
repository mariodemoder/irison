<?php

declare(strict_types=1);

namespace Modules\Notifications\Infrastructure\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Reminder;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Modules\Notifications\Application\DTOs\ReminderFilterData;
use Modules\Notifications\Application\UseCases\ListRemindersQuery;
use Modules\Notifications\Application\UseCases\ResendReminderCommand;
use Modules\Notifications\Application\UseCases\ShowReminderDetailQuery;

class ReminderController extends Controller
{
    public function __construct(
        private readonly ListRemindersQuery $listQuery,
        private readonly ShowReminderDetailQuery $showQuery,
        private readonly ResendReminderCommand $resendCommand,
    ) {}

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

        return response()->json($this->listQuery->execute(ReminderFilterData::fromRequest($validated)));
    }

    public function show(string $reminder): JsonResponse
    {
        $reminder = Reminder::findOrFail((int) $reminder);
        Gate::authorize('view', $reminder);

        return response()->json($this->showQuery->execute($reminder));
    }

    public function resend(string $reminder): JsonResponse
    {
        $reminder = Reminder::findOrFail((int) $reminder);
        Gate::authorize('update', $reminder);

        try {
            $newReminder = $this->resendCommand->execute($reminder);
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
