<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Mail\SubscriptionStatusMail;
use App\Models\Clinic;
use App\Models\SubscriptionRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class SubscriptionRequestController extends Controller
{
    public function index(Request $request): View
    {
        $query = SubscriptionRequest::query()
            ->with(['clinic:id,name', 'requester:id,name'])
            ->orderByDesc('created_at');

        if ($request->query('status')) {
            $query->where('status', $request->query('status'));
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

        $data = $request->validate([
            'reviewer_comments' => 'nullable|string|max:2000',
        ]);

        $clinic = $subscriptionRequest->clinic;
        $plan = $subscriptionRequest->requested_plan;

        $clinic->plan = $plan;
        $clinic->max_users = Clinic::PLAN_USER_LIMITS[$plan] ?? $clinic->max_users;
        $clinic->save();

        $subscriptionRequest->status = 'approved';
        $subscriptionRequest->reviewed_by = $request->user('admin')->id;
        $subscriptionRequest->reviewed_at = now();
        $subscriptionRequest->reviewer_comments = $data['reviewer_comments'] ?? null;
        $subscriptionRequest->save();

        $this->sendStatusMail($subscriptionRequest);

        return redirect()->route('backoffice.subscription-requests.index')
            ->with('status', 'Solicitud aprobada. La clínica ha sido actualizada al plan ' . strtoupper($plan) . '.');
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

        $this->sendStatusMail($subscriptionRequest);

        return redirect()->route('backoffice.subscription-requests.index')
            ->with('status', 'Solicitud rechazada.');
    }

    private function sendStatusMail(SubscriptionRequest $subscriptionRequest): void
    {
        $clinic = $subscriptionRequest->clinic;
        $clinicEmail = $clinic->email;

        if ($clinicEmail && filter_var($clinicEmail, FILTER_VALIDATE_EMAIL)) {
            try {
                Mail::to($clinicEmail)->queue(new SubscriptionStatusMail($subscriptionRequest));
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('No se pudo enviar SubscriptionStatusMail', [
                    'clinic_id' => $clinic->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
