<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionRequest;
use App\Services\Subscription\SubscriptionRequestService;
use App\Services\Subscription\SubscriptionUpgradeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionRequestController extends Controller
{
    public function __construct(
        private readonly SubscriptionRequestService $requestService,
        private readonly SubscriptionUpgradeService $upgradeService,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $requestData = $request->validate([
            'current_plan' => 'nullable|string|in:basic,pro,enterprise',
            'requested_plan' => 'required|string|in:basic,pro,enterprise',
            'comments' => 'nullable|string|max:2000',
        ]);

        $currentPlan = strtolower(trim((string) ($requestData['current_plan'] ?? $request->user()?->clinic?->plan ?? 'basic')));
        $requestedPlan = strtolower(trim((string) $requestData['requested_plan']));

        if (! $this->isValidUpgrade($currentPlan, $requestedPlan)) {
            return response()->json([
                'message' => 'El plan solicitado debe ser superior al plan actual.',
            ], 422);
        }

        $requestData['current_plan'] = $currentPlan;
        $requestData['requested_plan'] = $requestedPlan;

        try {
            $subscriptionRequest = $this->requestService->createRequest(
                $requestData,
                $request->user()?->clinic_id,
                $request->user()?->id,
            );

            return response()->json([
                'message' => 'Solicitud de suscripción creada correctamente.',
                'id' => $subscriptionRequest->id,
            ], 201);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Error al crear solicitud: ' . $e->getMessage()], 500);
        }
    }

    public function approve(int $id): JsonResponse
    {
        $request = SubscriptionRequest::findOrFail($id);

        if ($request->status !== 'pending') {
            return response()->json(['message' => 'Esta solicitud ya ha sido procesada.'], 422);
        }

        try {
            $this->upgradeService->approveAndGenerateCheckout($request);

            return response()->json(['message' => 'Solicitud aprobada. Enlace de pago generado.'], 200);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Error al aprobar solicitud: ' . $e->getMessage()], 500);
        }
    }

    public function reject(int $id, Request $request): JsonResponse
    {
        $requestData = $request->validate([
            'reviewer_comments' => 'nullable|string|max:2000',
        ]);

        $subscriptionRequest = SubscriptionRequest::findOrFail($id);

        if ($subscriptionRequest->status !== 'pending') {
            return response()->json(['message' => 'Esta solicitud ya ha sido procesada.'], 422);
        }

        $subscriptionRequest->status = 'rejected';
        $subscriptionRequest->reviewer_comments = $requestData['reviewer_comments'] ?? null;
        $subscriptionRequest->save();

        return response()->json(['message' => 'Solicitud rechazada.'], 200);
    }

    private function isValidUpgrade(string $currentPlan, string $requestedPlan): bool
    {
        $levels = [
            'basic' => 1,
            'pro' => 2,
            'enterprise' => 3,
        ];

        if (! isset($levels[$currentPlan], $levels[$requestedPlan])) {
            return false;
        }

        return $levels[$requestedPlan] > $levels[$currentPlan];
    }
}