<?php

declare(strict_types=1);

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\Clinic;
use App\Models\Subscription;
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

        return view('backoffice.dashboard', [
            'metrics' => $metrics,
            'admin' => request()->user('admin'),
        ]);
    }
}
