<?php

namespace Modules\Subscriptions\Infrastructure\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Modules\Subscriptions\Application\Services\SubscriptionRequestService;
use Modules\Subscriptions\Application\Services\SubscriptionUpgradeService;
use Modules\Subscriptions\Infrastructure\Mail\SubscriptionUpgradedNotificationMail;
use Modules\Subscriptions\Infrastructure\Payment\Resolver;
use Stripe\StripeClient;

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

        $clinic = $request->user()?->clinic;

        if ($clinic && $clinic->isTrialActive()) {
            return response()->json([
                'message' => 'Debes tener un plan Basic activo para solicitar una mejora de plan.',
            ], 422);
        }

        $currentPlan = strtolower(trim((string) ($requestData['current_plan'] ?? $clinic?->plan ?? 'basic')));
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

    public function confirmUpgrade(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'session_id' => 'nullable|string',
        ]);

        $clinic = $request->user()?->clinic;
        if (! $clinic) {
            return response()->json(['message' => 'Clínica no disponible'], 403);
        }

        $sessionId = trim((string) ($validated['session_id'] ?? ''));

        // Buscar la solicitud de upgrade por session_id o por clinic + waiting_payment
        $subscriptionRequest = null;

        if ($sessionId !== '') {
            $subscriptionRequest = SubscriptionRequest::where('stripe_checkout_session_id', $sessionId)
                ->where('clinic_id', $clinic->id)
                ->first();
        }

        if (! $subscriptionRequest) {
            $subscriptionRequest = SubscriptionRequest::where('clinic_id', $clinic->id)
                ->where('status', 'waiting_payment')
                ->latest()
                ->first();
        }

        if (! $subscriptionRequest) {
            return response()->json(['message' => 'No se encontró una solicitud de upgrade pendiente.'], 404);
        }

        // Si ya está completada, solo resolver la factura
        if ($subscriptionRequest->status === 'completed') {
            $invoiceUrl = $this->resolveInvoiceUrl($subscriptionRequest, $sessionId);

            return response()->json([
                'invoice_url' => $invoiceUrl,
                'plan' => $subscriptionRequest->requested_plan,
                'clinic_name' => $subscriptionRequest->clinic->name ?? '-',
            ]);
        }

        // Procesar el upgrade si aún no se ha completado
        $wasAlreadyCompleted = $subscriptionRequest->status === 'completed';

        try {
            $this->upgradeService->handlePaymentCompleted($subscriptionRequest, [
                'provider' => 'stripe',
                'session_id' => $sessionId,
            ]);
        } catch (\Throwable $e) {
            Log::warning('confirmUpgrade: handlePaymentCompleted falló, continuando con resolución de factura', [
                'request_id' => $subscriptionRequest->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Refrescar el modelo después del procesamiento
        $subscriptionRequest->refresh();

        $invoiceUrl = $this->resolveInvoiceUrl($subscriptionRequest, $sessionId);

        // Enviar email SOLO si esta llamada completó el upgrade (evitar duplicados con webhook)
        if (! $wasAlreadyCompleted && $subscriptionRequest->status === 'completed') {
            $this->sendUpgradeEmailBackup($subscriptionRequest, $invoiceUrl);
        }

        return response()->json([
            'invoice_url' => $invoiceUrl,
            'plan' => $subscriptionRequest->requested_plan,
            'clinic_name' => $subscriptionRequest->clinic->name ?? '-',
        ]);
    }

    private function sendUpgradeEmailBackup(SubscriptionRequest $subscriptionRequest, ?string $invoiceUrl): void
    {
        try {
            $recipient = $subscriptionRequest->clinic->ownerUser()->first()
                ?? $subscriptionRequest->clinic->users()->orderBy('id')->first();

            if ($recipient && filter_var((string) $recipient->email, FILTER_VALIDATE_EMAIL)) {
                Mail::to($recipient->email)->send(
                    new SubscriptionUpgradedNotificationMail($subscriptionRequest, $invoiceUrl)
                );
            }
        } catch (\Throwable $e) {
            Log::warning('confirmUpgrade: no se pudo enviar email de upgrade como backup', [
                'request_id' => $subscriptionRequest->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function resolveInvoiceUrl(SubscriptionRequest $request, string $sessionId): ?string
    {
        try {
            $stripe = new StripeClient(config('services.stripe.secret'));

            // Prioridad 1: sesión de checkout del request
            if (! empty($request->stripe_checkout_session_id)) {
                $session = $stripe->checkout->sessions->retrieve($request->stripe_checkout_session_id);
                if (! empty($session->invoice)) {
                    $invoice = $stripe->invoices->retrieve($session->invoice);
                    return $invoice->hosted_invoice_url ?? null;
                }
            }

            // Prioridad 2: sesión por el sessionId recibido
            if ($sessionId !== '') {
                $session = $stripe->checkout->sessions->retrieve($sessionId);
                if (! empty($session->invoice)) {
                    $invoice = $stripe->invoices->retrieve($session->invoice);
                    return $invoice->hosted_invoice_url ?? null;
                }
            }

            // Prioridad 3: última factura del customer
            $customerId = $request->clinic->stripe_id ?? $request->clinic->stripe_customer_id ?? null;
            if ($customerId) {
                $invoices = $stripe->invoices->all(['customer' => $customerId, 'limit' => 1]);
                if (count($invoices->data) > 0) {
                    return $invoices->data[0]->hosted_invoice_url ?? null;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('confirmUpgrade: no se pudo resolver URL de factura', [
                'request_id' => $request->id,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
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