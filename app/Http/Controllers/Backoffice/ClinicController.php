<?php

declare(strict_types=1);

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Http\Requests\Backoffice\ClinicActionRequest;
use App\Http\Requests\Backoffice\UpdateClinicRequest;
use App\Models\Clinic;
use App\Services\Backoffice\ClinicManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClinicController extends Controller
{
    public function __construct(private readonly ClinicManagementService $clinicManagementService)
    {
    }

    public function index(Request $request): View
    {
        return view('backoffice.clinics.index', [
            'clinics' => $this->clinicManagementService->listClinics($request->query()),
            'filters' => [
                'q' => (string) $request->query('q', ''),
                'status' => (string) $request->query('status', ''),
                'plan' => (string) $request->query('plan', ''),
            ],
        ]);
    }

    public function show(Clinic $clinic): View
    {
        return view('backoffice.clinics.show', [
            'clinic' => $clinic,
            'activity' => $this->clinicManagementService->recentActivity($clinic),
        ]);
    }

    public function edit(Clinic $clinic): View
    {
        return view('backoffice.clinics.edit', [
            'clinic' => $clinic,
        ]);
    }

    public function update(UpdateClinicRequest $request, Clinic $clinic): RedirectResponse
    {
        $this->clinicManagementService->updateClinic(
            $request->user('admin'),
            $clinic,
            $request->validated()
        );

        return redirect()->route('backoffice.clinics.show', $clinic)
            ->with('status', 'Clínica actualizada correctamente.');
    }

    public function extendTrial(ClinicActionRequest $request, Clinic $clinic): RedirectResponse
    {
        $days = (int) ($request->validated()['days'] ?? 7);
        $reason = $request->validated()['reason'] ?? null;

        $this->clinicManagementService->extendTrial($request->user('admin'), $clinic, $days, $reason);

        return redirect()->route('backoffice.clinics.show', $clinic)
            ->with('status', 'Trial extendido correctamente.');
    }

    public function suspend(ClinicActionRequest $request, Clinic $clinic): RedirectResponse
    {
        $reason = $request->validated()['reason'] ?? null;

        $this->clinicManagementService->suspend($request->user('admin'), $clinic, $reason);

        return redirect()->route('backoffice.clinics.show', $clinic)
            ->with('status', 'Clínica suspendida.');
    }

    public function reactivate(ClinicActionRequest $request, Clinic $clinic): RedirectResponse
    {
        $reason = $request->validated()['reason'] ?? null;

        $this->clinicManagementService->reactivate($request->user('admin'), $clinic, $reason);

        return redirect()->route('backoffice.clinics.show', $clinic)
            ->with('status', 'Clínica reactivada.');
    }

    public function cancelSubscription(ClinicActionRequest $request, Clinic $clinic): RedirectResponse
    {
        $reason = $request->validated()['reason'] ?? null;

        $clinic = $this->clinicManagementService->cancelSubscription($request->user('admin'), $clinic, $reason);

        $daysLeft = $clinic->cancellationGraceDaysLeft();
        $daysText = $daysLeft === null
            ? 'sin periodo pagado pendiente'
            : ($daysLeft === 1 ? '1 día' : $daysLeft . ' días');

        return redirect()->route('backoffice.clinics.show', $clinic)
            ->with('status', 'Suscripción cancelada. Quedan ' . $daysText . ' de uso pagado; luego entrará en modo solo lectura.');
    }

    public function changePlan(ClinicActionRequest $request, Clinic $clinic): RedirectResponse
    {
        $validated = $request->validated();
        $plan = (string) ($validated['plan'] ?? 'basic');
        $reason = $validated['reason'] ?? null;

        $this->clinicManagementService->changePlan($request->user('admin'), $clinic, $plan, $reason);

        return redirect()->route('backoffice.clinics.show', $clinic)
            ->with('status', 'Plan actualizado correctamente.');
    }

    public function impersonate(Request $request, Clinic $clinic): RedirectResponse
    {
        $result = $this->clinicManagementService->startImpersonation($request->user('admin'), $clinic);

        $frontendBase = rtrim((string) config('app.frontend_url', (string) env('FRONTEND_URL', 'http://localhost:5173')), '/');
        $targetUrl = $frontendBase . '/impersonate?token=' . urlencode((string) $result['token']) . '&clinic_id=' . (int) $clinic->id;

        return redirect()->away($targetUrl);
    }

    public function stopImpersonation(Request $request): RedirectResponse
    {
        $this->clinicManagementService->stopImpersonation($request->user('admin'));

        return redirect()->route('backoffice.clinics.index')
            ->with('status', 'Impersonación finalizada y token revocado.');
    }
}
