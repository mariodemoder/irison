<?php

namespace App\Http\Controllers\Api;

use App\Models\BillingPayment;
use App\Services\Counters\CounterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\Browsershot\Browsershot;

class MeController
{
    public function __construct(private readonly CounterService $counterService)
    {
    }

    public function __invoke(Request $request)
    {
        $user = $request->user();
        if ($user) {
            $user->load('profile:id,name,slug');
        }
        $clinic = $user ? $user->clinic : null;
        $invoiceBackgroundUrl = $clinic && $clinic->invoice_background_path
            ? Storage::url($clinic->invoice_background_path)
            : null;

            $status = 'blocked';
            $readOnlyNoTransactions = false;
            if ($clinic) {
                $tenantStatus = strtolower(trim((string) ($clinic->status ?? '')));
                $readOnlyNoTransactions = $clinic->isInReadOnlyNoTransactionsWindow();
                $clinicStatus = strtolower(trim((string) ($clinic->subscription_status ?? 'inactive')));

                if ($tenantStatus === 'trial_read_only') {
                    $readOnlyNoTransactions = true;
                    $status = 'trial_read_only';
                } elseif ($tenantStatus === 'churned') {
                    $readOnlyNoTransactions = false;
                    $status = 'blocked';
                } else {
                    $status = match ($clinicStatus) {
                        'active' => 'active',
                        'past_due' => 'blocked',
                        'canceled', 'cancelled' => ($clinic->isInCancellationPaidWindow() || $readOnlyNoTransactions)
                            ? 'canceled'
                            : 'blocked',
                        'trial', 'trial_warning' => $clinic->isTrialActive()
                            ? 'trial'
                            : ($readOnlyNoTransactions ? 'trial_read_only' : 'blocked'),
                        default => 'blocked',
                    };
                }
            }

        $trialEnds = null;
        $cancellationGraceEndsAt = null;
        $cancellationDaysLeft = null;
        $cancellationReadOnlyDaysLeft = null;
            if ($clinic) {
                $trialEnds = $clinic->trial_ends_at;
                $cancellationGraceEndsAt = $clinic->isInCancellationGracePeriod()
                    ? $clinic->currentSubscription()?->current_period_end
                    : null;
                $cancellationDaysLeft = $clinic->cancellationGraceDaysLeft();
                $cancellationReadOnlyDaysLeft = $clinic->cancellationReadOnlyDaysLeft();
            }

        $subscriptionPayments = [];
        if ($clinic && $status === 'active') {
            $paymentColumns = ['id', 'counter', 'amount', 'currency', 'status', 'created_at'];
            $hasBillingMethod = Schema::hasColumn('billing_payments', 'method');
            if ($hasBillingMethod) {
                $paymentColumns[] = 'method';
            }

            $subscriptionPayments = BillingPayment::query()
                ->where('clinic_id', (int) $clinic->id)
                ->whereIn('status', ['paid', 'completed'])
                ->orderByDesc('created_at')
                ->limit(20)
                ->get($paymentColumns)
                ->map(function (BillingPayment $payment) use ($hasBillingMethod) {
                    return [
                        'id' => $payment->id,
                        'counter' => $payment->counter,
                        'amount' => (int) $payment->amount,
                        'currency' => $payment->currency,
                        'status' => $payment->status,
                        'method' => $hasBillingMethod ? ($payment->method ?? null) : null,
                        'created_at' => $payment->created_at,
                    ];
                })
                ->values()
                ->toArray();
        }

        $ownerName = $clinic?->ownerUser?->name ?? $clinic?->name ?? '';

        $payload = [
            'user' => $user,
            'clinic' => $clinic,
            'clinic_owner_name' => $ownerName,
            'clinic_invoice_background_url' => $invoiceBackgroundUrl,
            'counters' => $clinic ? $this->counterService->getProfileCounters((int) $clinic->id) : [],
            'subscription_payments' => $subscriptionPayments,
            'status' => $status,
            'read_only_no_transactions' => $readOnlyNoTransactions,
                'can_transact' => $clinic
                    ? ($status === 'active'
                        || $status === 'trial'
                        || ($status === 'canceled' && $clinic->isInCancellationPaidWindow()))
                    : false,
            'trial_ends_at' => $trialEnds,
            'cancellation_grace_ends_at' => $cancellationGraceEndsAt,
            'cancellation_days_left' => $cancellationDaysLeft,
            'cancellation_read_only_days_left' => $cancellationReadOnlyDaysLeft,
        ];

        if ($status === 'canceled') {
            $payload['code'] = 'SUBSCRIPTION_CANCELED';
            $payload['message'] = $clinic && $clinic->isInCancellationPaidWindow()
                ? 'Suscripción cancelada. Mantienes acceso completo hasta el fin de tu periodo pagado.'
                : 'Suscripción cancelada: modo solo lectura durante el periodo de gracia. Si no reactivas, perderás tus datos al finalizar el plazo.';
        }

        if ($status === 'blocked') {
            $payload['code'] = 'SUBSCRIPTION_REQUIRED';
            $payload['message'] = 'Tu periodo de prueba ha finalizado';

            if ($clinic && strtolower(trim((string) ($clinic->status ?? ''))) === 'churned') {
                $payload['code'] = 'TRIAL_CHURNED';
                $payload['message'] = 'Tu trial finalizó sin conversión. Contacta soporte para reactivar tu cuenta.';
            }
        }

        if ($status === 'trial_read_only') {
            $payload['code'] = 'TRIAL_READ_ONLY_NO_TRANSACTIONS';
            $payload['message'] = 'Tu periodo de prueba ha finalizado. Dispones de 7 días en modo solo lectura sin transacciones.';
        }

        return response()->json($payload);

    }

    public function update(Request $request)
    {
        $user = $request->user();
        $clinic = $user ? $user->clinic : null;

        if (!$user || !$clinic) {
            return response()->json([
                'message' => 'Usuario o clínica no disponible',
            ], 403);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'clinic' => ['nullable', 'array'],
            'clinic.name' => ['nullable', 'string', 'max:255'],
            'clinic.email' => ['nullable', 'email', 'max:255'],
            'clinic.phone' => ['nullable', 'string', 'max:50'],
            'clinic.nif' => ['nullable', 'string', 'max:50'],
            'clinic.address' => ['nullable', 'string', 'max:255'],
            'clinic.locality' => ['nullable', 'string', 'max:120'],
            'clinic.province' => ['nullable', 'string', 'max:120'],
            'clinic.country' => ['nullable', 'string', 'max:120'],
            'clinic.zip' => ['nullable', 'string', 'max:20'],
            'clinic.theme_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'clinic.business_hours' => ['nullable', 'array'],
            'clinic.business_hours.*.day' => ['required', Rule::in(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'])],
            'clinic.business_hours.*.enabled' => ['required', 'boolean'],
            'clinic.business_hours.*.start' => ['nullable', 'date_format:H:i'],
            'clinic.business_hours.*.end' => ['nullable', 'date_format:H:i'],
            'clinic.closed_days' => ['nullable', 'array'],
            'clinic.closed_days.*' => ['required', 'string', 'regex:/^\d{4}-\d{2}-\d{2}(\.\.\d{4}-\d{2}-\d{2})?$/'],
            'counters' => ['nullable', 'array'],
            'counters.*.table_type' => ['required', Rule::in(CounterService::TABLE_TYPES)],
            'counters.*.prefix' => ['required', 'string', 'min:1', 'max:4', 'regex:/^[A-Za-z0-9]+$/'],
            'counters.*.last_number' => ['nullable', 'integer', 'min:0'],
        ]);

        DB::transaction(function () use ($data, $user, $clinic) {
            $user->update([
                'name' => $data['name'],
                'email' => $data['email'],
            ]);

            $clinicPayload = $data['clinic'] ?? [];
            $businessHours = array_values(array_map(static function ($item) {
                return [
                    'day' => (string) ($item['day'] ?? ''),
                    'enabled' => (bool) ($item['enabled'] ?? false),
                    'start' => !empty($item['start']) ? (string) $item['start'] : null,
                    'end' => !empty($item['end']) ? (string) $item['end'] : null,
                ];
            }, $clinicPayload['business_hours'] ?? []));

            $closedDays = array_values(array_unique(array_filter(array_map(static function ($item) {
                $value = trim((string) $item);
                return preg_match('/^\d{4}-\d{2}-\d{2}(\.\.\d{4}-\d{2}-\d{2})?$/', $value) ? $value : null;
            }, $clinicPayload['closed_days'] ?? []))));

            $clinic->update([
                'name' => array_key_exists('name', $clinicPayload) ? $clinicPayload['name'] : $clinic->name,
                'email' => $clinicPayload['email'] ?? null,
                'phone' => $clinicPayload['phone'] ?? null,
                'nif' => $clinicPayload['nif'] ?? null,
                'address' => $clinicPayload['address'] ?? null,
                'locality' => $clinicPayload['locality'] ?? null,
                'province' => $clinicPayload['province'] ?? null,
                'country' => $clinicPayload['country'] ?? null,
                'zip' => $clinicPayload['zip'] ?? null,
                'theme_color' => $clinicPayload['theme_color'] ?? null,
                'business_hours' => $businessHours,
                'closed_days' => $closedDays,
            ]);

            if (!empty($data['counters']) && is_array($data['counters'])) {
                $this->counterService->upsertClinicCounters((int) $clinic->id, $data['counters']);
            }

        }, 3);

        return response()->json([
            'user' => $user->fresh(),
            'clinic' => $clinic->fresh(),
            'clinic_invoice_background_url' => $clinic->invoice_background_path
                ? Storage::url($clinic->invoice_background_path)
                : null,
            'counters' => $this->counterService->getProfileCounters((int) $clinic->id),
            'message' => 'Datos actualizados',
        ]);
    }

    public function uploadInvoiceBackground(Request $request)
    {
        $user = $request->user();
        $clinic = $user ? $user->clinic : null;

        if (!$user || !$clinic) {
            return response()->json([
                'message' => 'Usuario o clínica no disponible',
            ], 403);
        }

        $data = $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        if ($clinic->invoice_background_path && Storage::disk('public')->exists($clinic->invoice_background_path)) {
            Storage::disk('public')->delete($clinic->invoice_background_path);
        }

        $path = $data['image']->store('invoice-backgrounds', 'public');

        $clinic->invoice_background_path = $path;
        $clinic->save();

        return response()->json([
            'message' => 'Fondo de factura actualizado',
            'invoice_background_path' => $path,
            'invoice_background_url' => Storage::url($path),
        ]);
    }

    public function deleteInvoiceBackground(Request $request)
    {
        $user = $request->user();
        $clinic = $user ? $user->clinic : null;

        if (!$user || !$clinic) {
            return response()->json([
                'message' => 'Usuario o clínica no disponible',
            ], 403);
        }

        if ($clinic->invoice_background_path && Storage::disk('public')->exists($clinic->invoice_background_path)) {
            Storage::disk('public')->delete($clinic->invoice_background_path);
        }

        $clinic->invoice_background_path = null;
        $clinic->save();

        return response()->json([
            'message' => 'Fondo de factura eliminado',
            'invoice_background_path' => null,
            'invoice_background_url' => null,
        ]);
    }

    public function previewInvoiceBackgroundPdf(Request $request): Response|JsonResponse
    {
        $user = $request->user();
        $clinic = $user ? $user->clinic : null;

        if (!$user || !$clinic) {
            return response()->json([
                'message' => 'Usuario o clínica no disponible',
            ], 403);
        }

        $data = $request->validate([
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $invoiceBackgroundDataUri = null;
        $backgroundIsA4 = false;

        if (!empty($data['image'])) {
            $image = $data['image'];
            $binary = file_get_contents($image->getRealPath());
            $mime = $image->getMimeType() ?: 'image/png';

            if ($binary !== false) {
                $invoiceBackgroundDataUri = 'data:' . $mime . ';base64,' . base64_encode($binary);
                $backgroundIsA4 = $this->isA4LikeImage($binary);
            }
        } elseif (!empty($clinic->invoice_background_path) && Storage::disk('public')->exists($clinic->invoice_background_path)) {
            $path = (string) $clinic->invoice_background_path;
            $content = Storage::disk('public')->get($path);
            $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));

            $mime = match ($extension) {
                'jpg', 'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'webp' => 'image/webp',
                default => 'application/octet-stream',
            };

            $invoiceBackgroundDataUri = 'data:' . $mime . ';base64,' . base64_encode($content);
            $backgroundIsA4 = $this->isA4LikeImage($content);
        }

        $now = now();
        $patient = (object) [
            'name' => 'Paciente de ejemplo',
            'nif' => '12345678A',
            'email' => 'paciente.demo@example.com',
            'phone' => '600123123',
            'address' => 'Calle Demo 123',
            'zip' => '28001',
        ];

        $document = (object) [
            'id' => 0,
            'counter' => 'FR-000321',
            'type' => 'invoice',
            'typeinvoice' => 'package',
            'date' => $now,
            'created_at' => $now,
            'clinic_name' => $clinic->name ?: 'Clínica Demo',
            'clinic_nif' => $clinic->nif ?: 'B12345678',
            'clinic_address' => $clinic->address ?: 'Calle Salud 42',
            'clinic_zip' => $clinic->zip ?: '28001',
            'clinic_province' => $clinic->province ?: 'Madrid',
            'clinic_country' => $clinic->country ?: 'España',
            'user_full_name' => $user->name ?: 'Profesional Demo',
            'status' => 'issued',
            'notes' => 'Bono Fisioterapia 10 sesiones',
            'amount' => 120.00,
            'patient_full_name' => $patient->name,
            'patient_nif' => $patient->nif,
            'patient_email' => $patient->email,
            'patient_phone' => $patient->phone,
            'patient_address' => $patient->address,
            'patient_zip' => $patient->zip,
            'patient' => $patient,
        ];

        $bonus = (object) [
            'name' => 'Bono Fisioterapia 10 sesiones',
            'total_sessions' => 10,
            'expires_at' => $now->copy()->addYear(),
        ];

        $html = view('pdf.document-invoice', [
            'document' => $document,
            'invoiceBackgroundDataUri' => $invoiceBackgroundDataUri,
            'bonus' => $bonus,
        ])->render();

        if ($request->query('format') === 'html') {
            return response($html, 200, [
                'Content-Type' => 'text/html; charset=utf-8',
            ]);
        }

        $browsershot = Browsershot::html($html)
            ->format('A4')
            ->margins($backgroundIsA4 ? 0 : 10, $backgroundIsA4 ? 0 : 10, $backgroundIsA4 ? 0 : 10, $backgroundIsA4 ? 0 : 10)
            ->showBackground()
            ->setNodeModulePath(base_path('node_modules'));

        $pdfBinary = $browsershot->pdf();

        return response($pdfBinary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="factura-preview-demo.pdf"',
        ]);
    }

    private function isA4LikeImage(string $binary): bool
    {
        $size = @getimagesizefromstring($binary);
        if ($size === false || empty($size[0]) || empty($size[1])) {
            return false;
        }

        $width = (float) $size[0];
        $height = (float) $size[1];
        if ($width <= 0 || $height <= 0) {
            return false;
        }

        $ratio = max($width, $height) / min($width, $height);
        $a4Ratio = 1.41421356;

        return abs($ratio - $a4Ratio) <= 0.03;
    }
}
