<?php

declare(strict_types=1);

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\BillingPayment;
use App\Models\Clinic;
use App\Models\Subscription;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $metrics = [
            'totalClinics' => (int) Clinic::query()->count('*'),
            'activeClinics' => (int) Clinic::query()->where('subscription_status', 'active')->count('*'),
            'trialEndingSoon' => (int) Clinic::query()
                ->where('subscription_status', 'trial')
                ->whereBetween('trial_ends_at', [now(), now()->copy()->addDays(7)])
                ->count('*'),
            'pastDueClinics' => (int) Clinic::query()->where('subscription_status', 'past_due')->count('*'),
            'canceledGrace' => (int) Subscription::query()
                ->where(function ($query): void {
                    $query->where('status', 'canceled')
                        ->orWhere('status', 'cancelled');
                })
                ->whereNotNull('current_period_end')
                ->where('current_period_end', '>=', now())
                ->distinct('clinic_id')
                ->count('clinic_id'),
            'internalAdmins' => (int) AdminUser::query()->count('*'),
            'activeInternalAdmins' => (int) AdminUser::query()->where('is_active', true)->count('*'),
        ];

        $failedPaymentAlerts = $this->resolveFailedPaymentAlerts();

        $trialEndingAlerts = Clinic::query()
            ->whereIn('subscription_status', ['trial', 'trial_warning'])
            ->whereNotNull('trial_ends_at')
            ->whereBetween('trial_ends_at', [now(), now()->copy()->addDays(5)])
            ->orderByDesc('trial_ends_at')
            ->limit(100)
            ->get(['id', 'name', 'subscription_status', 'trial_ends_at']);

        return view('backoffice.dashboard', [
            'metrics' => $metrics,
            'admin' => request()->user('admin'),
            'failedPaymentAlerts' => $failedPaymentAlerts,
            'trialEndingAlerts' => $trialEndingAlerts,
        ]);
    }

    private function resolveFailedPaymentAlerts(): Collection
    {
        $recentByClinic = BillingPayment::query()
            ->select(['clinic_id', 'status', 'created_at'])
            ->orderByDesc('id')
            ->get()
            ->groupBy('clinic_id');

        $alertClinicIds = collect();
        foreach ($recentByClinic as $clinicId => $rows) {
            $latestThree = $rows->take(3);
            if ($latestThree->count() < 3) {
                continue;
            }

            if ($latestThree->every(static fn (BillingPayment $row): bool => strtolower((string) $row->status) === 'failed')) {
                $alertClinicIds->push((int) $clinicId);
            }
        }

        if ($alertClinicIds->isEmpty()) {
            return collect();
        }

        $clinics = Clinic::query()
            ->whereIn('id', $alertClinicIds->values())
            ->get(['id', 'name'])
            ->keyBy('id');

        return $alertClinicIds
            ->map(function (int $clinicId) use ($recentByClinic, $clinics): ?array {
                $clinic = $clinics->get($clinicId);
                if (! $clinic) {
                    return null;
                }

                /** @var \Illuminate\Support\Collection<int, BillingPayment> $rows */
                $rows = $recentByClinic->get($clinicId, collect());
                $latestThree = $rows->take(3);

                return [
                    'clinic_id' => (int) $clinic->id,
                    'clinic_name' => (string) $clinic->name,
                    'failed_count' => (int) $latestThree->count(),
                    'last_failed_at' => $latestThree->first()?->created_at,
                ];
            })
            ->filter()
            ->sortByDesc(static fn (array $row) => $row['last_failed_at'])
            ->values();
    }
}
