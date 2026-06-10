<?php

declare(strict_types=1);

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Http\Requests\Backoffice\ClinicActionRequest;
use App\Http\Requests\Backoffice\UpdateClinicRequest;
use App\Models\ActivityLog;
use App\Models\Appointment;
use App\Models\BillingPayment;
use App\Models\Clinic;
use App\Models\Document;
use App\Models\Payment;
use App\Models\Reminder;
use App\Models\User;
use App\Services\Backoffice\ClinicManagementService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Stripe\Stripe;
use Stripe\StripeClient;
use Throwable;

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
        $lastStripePayment = BillingPayment::query()
            ->where('clinic_id', $clinic->id)
            ->where('provider', 'stripe')
            ->whereIn('status', ['paid', 'completed'])
            ->latest('created_at')
            ->first(['created_at']);

        $lastActivityCandidates = collect([
            Appointment::query()->where('clinic_id', $clinic->id)->max('created_at'),
            Payment::query()->where('clinic_id', $clinic->id)->max('paid_at'),
            Document::query()->where('clinic_id', $clinic->id)->max('date'),
        ])->filter();

        $lastClinicActivityAt = $lastActivityCandidates
            ->map(static fn ($value) => $value instanceof \DateTimeInterface
                ? Carbon::instance($value)
                : Carbon::parse((string) $value)
            )
            ->sortDesc()
            ->first();

        $lastTenantLoginAt = User::query()
            ->where('clinic_id', $clinic->id)
            ->whereNotNull('last_login_at')
            ->max('last_login_at');

        $lastDocumentCreatedAt = Document::query()
            ->where('clinic_id', $clinic->id)
            ->max('created_at');

        $last500ErrorAt = ActivityLog::query()
            ->where('tenant_id', $clinic->id)
            ->where('event', 'system_error_500')
            ->max('created_at');

        $activityLog = ActivityLog::query()
            ->where('tenant_id', $clinic->id)
            ->whereIn('event', [
                'login',
                'document_created',
                'system_error_500',
                'subscription_created',
                'subscription_renewed',
                'subscription_cancelled',
                'trial_extended',
            ])
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        $stripeBilling = $this->loadStripeInvoices($clinic);

        $notifications = Reminder::query()
            ->where('clinic_id', $clinic->id)
            ->with([
                'appointment:id,patient_id,start_time',
                'appointment.patient:id,first_name,last_name,phone,email',
            ])
            ->orderByDesc('sent_at')
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(function (Reminder $reminder): array {
                $patient = $reminder->appointment?->patient;
                $contactName = trim((string) (($patient?->first_name ?? '') . ' ' . ($patient?->last_name ?? '')));
                $appointmentDate = $reminder->appointment?->start_time?->format('Y-m-d H:i');
                $reminderType = trim((string) ($reminder->reminder_type ?? 'recordatorio'));

                return [
                    'date_time' => $reminder->sent_at ?? $reminder->created_at,
                    'contact_name' => $contactName !== '' ? $contactName : '-',
                    'contact_phone' => trim((string) ($patient?->phone ?? '')) ?: '-',
                    'contact_email' => trim((string) ($reminder->recipient_email ?? $patient?->email ?? '')) ?: '-',
                    'subject' => 'Recordatorio ' . ($reminderType !== '' ? $reminderType : '-'),
                    'body' => $appointmentDate
                        ? 'Recordatorio asociado a cita del ' . $appointmentDate
                        : (trim((string) ($reminder->error_message ?? '')) ?: 'Sin cuerpo registrado'),
                    'method' => strtolower(trim((string) ($reminder->channel ?? 'email'))),
                ];
            })
            ->values();

        return view('backoffice.clinics.show', [
            'clinic' => $clinic,
            'activity' => $this->clinicManagementService->recentActivity($clinic),
            'activityLog' => $activityLog,
            'lastStripePaymentAt' => $lastStripePayment?->created_at,
            'lastClinicActivityAt' => $lastClinicActivityAt,
            'lastTenantLoginAt' => $lastTenantLoginAt ? Carbon::parse((string) $lastTenantLoginAt) : null,
            'lastDocumentCreatedAt' => $lastDocumentCreatedAt ? Carbon::parse((string) $lastDocumentCreatedAt) : null,
            'last500ErrorAt' => $last500ErrorAt ? Carbon::parse((string) $last500ErrorAt) : null,
            'stripeInvoices' => $stripeBilling['invoices'],
            'stripeInvoicesError' => $stripeBilling['error'],
            'notifications' => $notifications,
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

    private function loadStripeInvoices(Clinic $clinic): array
    {
        try {
            $caBundlePath = config('services.stripe.ca_bundle')
                ?: ini_get('curl.cainfo')
                ?: base_path('vendor/stripe/stripe-php/data/ca-certificates.crt');

            if (is_string($caBundlePath) && $caBundlePath !== '' && is_file($caBundlePath)) {
                $normalizedPath = str_replace('\\', '/', $caBundlePath);
                Stripe::setCABundlePath($normalizedPath);
                putenv('SSL_CERT_FILE=' . $normalizedPath);
                putenv('CURL_CA_BUNDLE=' . $normalizedPath);
            }

            if (! config('services.stripe.verify_ssl', true)) {
                Stripe::setVerifySslCerts(false);
            }

            $stripe = new StripeClient((string) config('services.stripe.secret'));

            $customerIds = collect([
                trim((string) ($clinic->stripe_id ?? '')),
                trim((string) ($clinic->stripe_customer_id ?? '')),
            ])->filter()->values();

            $subscriptionCustomerIds = $clinic->saasSubscriptions()
                ->whereNotNull('stripe_customer_id')
                ->orderByDesc('id')
                ->pluck('stripe_customer_id')
                ->map(static fn ($id) => trim((string) $id))
                ->filter();

            $customerIds = $customerIds->merge($subscriptionCustomerIds)->unique()->values();

            // Fallback: si no hay customer sincronizado en DB, intentamos resolver por email en Stripe.
            if ($customerIds->isEmpty()) {
                $clinicEmail = trim((string) ($clinic->email ?? ''));
                if ($clinicEmail !== '') {
                    $customersByEmail = $stripe->customers->all([
                        'email' => $clinicEmail,
                        'limit' => 10,
                    ]);

                    $resolvedByEmail = collect($customersByEmail->data ?? [])
                        ->map(static fn ($customer) => trim((string) ($customer->id ?? '')))
                        ->filter()
                        ->values();

                    $customerIds = $customerIds->merge($resolvedByEmail)->unique()->values();
                }
            }

            if ($customerIds->isEmpty()) {
                return [
                    'invoices' => [],
                    'error' => 'La clínica no tiene customer de Stripe sincronizado (stripe_id/stripe_customer_id).',
                ];
            }

            $allInvoices = collect();
            foreach ($customerIds as $customerId) {
                $response = $stripe->invoices->all([
                    'customer' => (string) $customerId,
                    'limit' => 100,
                ]);

                $mapped = collect($response->data ?? [])->map(static function ($invoice): array {
                    return [
                        'id' => (string) ($invoice->id ?? ''),
                        'number' => (string) ($invoice->number ?? ''),
                        'status' => (string) ($invoice->status ?? ''),
                        'currency' => strtoupper((string) ($invoice->currency ?? 'EUR')),
                        'total' => (int) ($invoice->total ?? 0),
                        'amount_paid' => (int) ($invoice->amount_paid ?? 0),
                        'created_at' => isset($invoice->created)
                            ? Carbon::createFromTimestamp((int) $invoice->created)
                            : null,
                        'hosted_invoice_url' => (string) ($invoice->hosted_invoice_url ?? ''),
                        'invoice_pdf' => (string) ($invoice->invoice_pdf ?? ''),
                    ];
                });

                $allInvoices = $allInvoices->concat($mapped);
            }

            $invoices = $allInvoices
                ->unique('id')
                ->sortByDesc(static fn (array $invoice) => $invoice['created_at']?->getTimestamp() ?? 0)
                ->values()
                ->all();

            // Si logramos resolver por fallback, sincronizamos el primer customer para futuras consultas.
            if (empty($clinic->stripe_id) && empty($clinic->stripe_customer_id) && $customerIds->isNotEmpty()) {
                $firstCustomerId = (string) $customerIds->first();
                $clinic->forceFill([
                    'stripe_id' => $firstCustomerId,
                    'stripe_customer_id' => $firstCustomerId,
                ])->save();
            }

            return [
                'invoices' => $invoices,
                'error' => null,
            ];
        } catch (Throwable $exception) {
            return [
                'invoices' => [],
                'error' => 'No se pudieron cargar las facturas de Stripe para esta clínica.',
            ];
        }
    }
}
