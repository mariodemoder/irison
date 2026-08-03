<?php

declare(strict_types=1);

namespace Modules\Notifications\Infrastructure\Controllers;

use App\Http\Controllers\Controller;
use App\Models\EmailLog;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Modules\Notifications\Application\DTOs\EmailLogFilterData;
use Modules\Notifications\Application\UseCases\ListEmailLogsQuery;
use Modules\Notifications\Application\UseCases\ResendEmailLogCommand;
use Modules\Notifications\Application\UseCases\ShowEmailLogDetailQuery;

class EmailLogController extends Controller
{
    public function __construct(
        private readonly ListEmailLogsQuery $listQuery,
        private readonly ShowEmailLogDetailQuery $showQuery,
        private readonly ResendEmailLogCommand $resendCommand,
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', EmailLog::class);

        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:sent,failed'],
            'category' => ['nullable', 'string', 'max:50'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json($this->listQuery->execute(EmailLogFilterData::fromRequest($validated)));
    }

    public function show(string $log): JsonResponse
    {
        $log = EmailLog::findOrFail((int) $log);
        Gate::authorize('view', $log);

        return response()->json($this->showQuery->execute($log));
    }

    public function resend(string $log): JsonResponse
    {
        $log = EmailLog::findOrFail((int) $log);
        Gate::authorize('update', $log);

        try {
            $this->resendCommand->execute($log);
        } catch (DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Recordatorio reenviado correctamente.',
        ]);
    }
}
