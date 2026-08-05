<?php

namespace App\Http\Controllers\Backoffice;

use App\Events\SubscriptionRejected;
use App\Http\Controllers\Controller;
use App\Models\SubscriptionRequest;
use App\Services\PaymentProvider\Resolver;
use App\Services\Subscription\SubscriptionUpgradeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class SubscriptionRequestController extends Controller
{
    public function __construct(
        private readonly SubscriptionUpgradeService $upgradeService,
    ) {}

    public function index(Request $request): View
    {
        $query = SubscriptionRequest::query()
            ->with(['clinic:id,name', 'requester:id,name'])
            ->orderByDesc('created_at');

        if ($request->query('status')) {
            $status = (string) $request->query('status');

            if ($status === 'approved') {
                $query->whereIn('status', ['waiting_payment', 'paid', 'completed']);
            } else {
                $query->where('status', $status);
            }
        }

        return view('backoffice.subscription_requests.index', [
            'requests' => $query->paginate(20),
            'currentStatus' => $request->query('status', ''),
        ]);
    }

    public function approve(Request $request, SubscriptionRequest $subscriptionRequest): RedirectResponse
    {
        if ($subscriptionRequest->status !== 'pending') {
            return redirect()->route('backoffice.subscription-requests.index')
                ->with('status', 'Esta solicitud ya ha sido procesada.');
        }

        try {
            $data = $request->validate([
                'reviewer_comments' => 'nullable|string|max:2000',
            ]);
            $result = $this->upgradeService->approveAndGenerateCheckout(
                $subscriptionRequest,
                $request->user('admin')->id,
                $data['reviewer_comments'] ?? null,
            );

            $status = (string) ($subscriptionRequest->fresh()?->status ?? '');

            if ($status === 'completed') {
                return redirect()->route('backoffice.subscription-requests.index')
                    ->with('status', 'Solicitud aprobada y upgrade completado automáticamente (pago registrado).');
            }

            return redirect()->route('backoffice.subscription-requests.index')
                ->with('status', 'Solicitud aprobada. Se ha generado el enlace de pago para la clínica.');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Error al aprobar solicitud de upgrade', [
                'request_id' => $subscriptionRequest->id,
                'clinic_id' => $subscriptionRequest->clinic_id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('backoffice.subscription-requests.index')
                ->with('status', 'Error al aprobar solicitud: '.$e->getMessage());
        }
    }

    public function reject(Request $request, SubscriptionRequest $subscriptionRequest): RedirectResponse
    {
        if ($subscriptionRequest->status !== 'pending') {
            return redirect()->route('backoffice.subscription-requests.index')
                ->with('status', 'Esta solicitud ya ha sido procesada.');
        }

        $data = $request->validate([
            'reviewer_comments' => 'nullable|string|max:2000',
        ]);

        $subscriptionRequest->status = 'rejected';
        $subscriptionRequest->reviewed_by = $request->user('admin')->id;
        $subscriptionRequest->reviewed_at = now();
        $subscriptionRequest->reviewer_comments = $data['reviewer_comments'] ?? null;
        $subscriptionRequest->save();

        SubscriptionRejected::dispatch($subscriptionRequest);

        return redirect()->route('backoffice.subscription-requests.index')
            ->with('status', 'Solicitud rechazada.');
    }

    public function previewUpgrade(SubscriptionRequest $subscriptionRequest): JsonResponse
    {
        if ($subscriptionRequest->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'La solicitud ya ha sido procesada.',
            ]);
        }

        try {
            $provider = Resolver::resolve();
            $clinic = $subscriptionRequest->clinic;
            $subscription = $clinic->currentSubscription();

            $preview = $provider->previewUpgrade([
                'clinic' => $clinic,
                'current_plan' => $subscriptionRequest->current_plan,
                'new_plan' => $subscriptionRequest->requested_plan,
                'subscription_reference' => $subscription?->stripe_subscription_id,
            ]);

            return response()->json($preview);
        } catch (\Throwable $e) {
            Log::error('Error al obtener preview de upgrade', [
                'request_id' => $subscriptionRequest->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo calcular la vista previa: '.$e->getMessage(),
            ], 500);
        }
    }
}
