<?php

declare(strict_types=1);

namespace App\Services\Backoffice;

use App\Models\AdminUser;
use App\Models\BackofficeClinicActivity;
use App\Models\Clinic;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ClinicManagementService
{
    private ?bool $hasClinicActivitiesTable = null;

    public function listClinics(array $filters): LengthAwarePaginator
    {
        $query = Clinic::query()->orderByDesc('id');

        $q = trim((string) ($filters['q'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));
        $plan = trim((string) ($filters['plan'] ?? ''));

        if ($q !== '') {
            $query->where(function ($subQuery) use ($q): void {
                $subQuery->where('name', 'like', '%' . $q . '%')
                    ->orWhere('slug', 'like', '%' . $q . '%')
                    ->orWhere('email', 'like', '%' . $q . '%');
            });
        }

        if ($plan !== '') {
            $query->where('plan', $plan);
        }

        if ($status !== '') {
            $query->where(function ($statusQuery) use ($status): void {
                if ($status === 'suspended') {
                    $statusQuery->whereNotNull('suspended_at');

                    return;
                }

                if ($status === 'cancelled') {
                    $statusQuery->whereIn('subscription_status', ['canceled', 'cancelled']);

                    return;
                }

                if ($status === 'expired') {
                    $statusQuery->whereNotNull('trial_ends_at')
                        ->where('trial_ends_at', '<', now())
                        ->whereNull('suspended_at')
                        ->whereNotIn('subscription_status', ['active', 'past_due']);

                    return;
                }

                $statusQuery->where('subscription_status', $status)
                    ->whereNull('suspended_at');
            });
        }

        return $query->paginate(20)->withQueryString();
    }

    public function updateClinic(AdminUser $admin, Clinic $clinic, array $data): Clinic
    {
        $old = $clinic->only(['name', 'slug', 'email', 'phone', 'plan']);

        $clinic->fill($data);
        $clinic->save();

        $this->recordActivity($admin, $clinic, 'clinic.updated', [
            'before' => $old,
            'after' => $clinic->only(['name', 'slug', 'email', 'phone', 'plan']),
        ]);

        return $clinic->fresh();
    }

    public function extendTrial(AdminUser $admin, Clinic $clinic, int $days, ?string $reason = null): Clinic
    {
        $base = $clinic->trial_ends_at && $clinic->trial_ends_at->isFuture()
            ? $clinic->trial_ends_at->copy()
            : now();

        $clinic->trial_ends_at = $base->addDays($days);

        if (! in_array((string) $clinic->subscription_status, ['trial', 'active'], true)) {
            $clinic->subscription_status = 'trial';
        }

        $clinic->save();

        $this->recordActivity($admin, $clinic, 'clinic.trial.extended', [
            'days' => $days,
            'reason' => $reason,
            'trial_ends_at' => optional($clinic->trial_ends_at)->toDateTimeString(),
        ]);

        return $clinic->fresh();
    }

    public function suspend(AdminUser $admin, Clinic $clinic, ?string $reason = null): Clinic
    {
        $clinic->suspended_at = now();
        $clinic->status = 'suspended';
        $clinic->save();

        $this->recordActivity($admin, $clinic, 'clinic.suspended', [
            'reason' => $reason,
        ]);

        return $clinic->fresh();
    }

    public function reactivate(AdminUser $admin, Clinic $clinic, ?string $reason = null): Clinic
    {
        $clinic->suspended_at = null;
        $clinic->status = 'active';
        $clinic->save();

        $this->recordActivity($admin, $clinic, 'clinic.reactivated', [
            'reason' => $reason,
        ]);

        return $clinic->fresh();
    }

    public function cancelSubscription(AdminUser $admin, Clinic $clinic, ?string $reason = null): Clinic
    {
        $subscription = $clinic->currentSubscription();
        if (! $subscription) {
            $subscription = Subscription::query()->create([
                'clinic_id' => $clinic->id,
                'status' => 'canceled',
                'current_period_end' => now()->addDays(7),
            ]);
        } else {
            $subscription->status = 'canceled';
            $subscription->current_period_end = now()->addDays(7);
            $subscription->save();
        }

        $clinic->subscription_status = 'canceled';
        $clinic->subscribed_at = null;
        $clinic->save();

        $this->recordActivity($admin, $clinic, 'clinic.subscription.canceled', [
            'reason' => $reason,
            'subscription_id' => $subscription->id,
        ]);

        return $clinic->fresh();
    }

    public function changePlan(AdminUser $admin, Clinic $clinic, string $plan, ?string $reason = null): Clinic
    {
        $beforePlan = (string) $clinic->plan;

        $clinic->plan = $plan;
        $clinic->save();

        $this->recordActivity($admin, $clinic, 'clinic.plan.changed', [
            'from' => $beforePlan,
            'to' => $plan,
            'reason' => $reason,
        ]);

        return $clinic->fresh();
    }

    public function startImpersonation(AdminUser $admin, Clinic $clinic): array
    {
        $owner = $this->resolveOwner($clinic);
        if (! $owner) {
            throw new \RuntimeException('La clínica no tiene usuario owner para impersonar.');
        }

        return DB::transaction(function () use ($admin, $clinic, $owner): array {
            $token = $owner->createToken('admin-impersonate')->plainTextToken;
            $tokenModel = $owner->tokens()->latest('id')->first();

            session()->put('backoffice_impersonation', [
                'admin_user_id' => $admin->id,
                'clinic_id' => $clinic->id,
                'target_user_id' => $owner->id,
                'token_id' => $tokenModel?->id,
                'started_at' => now()->toIso8601String(),
            ]);

            $this->recordActivity($admin, $clinic, 'admin_impersonate.start', [
                'target_user_id' => $owner->id,
                'token_id' => $tokenModel?->id,
            ], $owner->id);

            return [
                'token' => $token,
                'target_user' => $owner,
            ];
        });
    }

    public function stopImpersonation(AdminUser $admin): void
    {
        $context = (array) session()->get('backoffice_impersonation', []);
        if (empty($context)) {
            return;
        }

        $tokenId = (int) ($context['token_id'] ?? 0);
        $clinicId = (int) ($context['clinic_id'] ?? 0);
        $targetUserId = (int) ($context['target_user_id'] ?? 0);

        if ($tokenId > 0) {
            DB::table('personal_access_tokens')->where('id', $tokenId)->delete();
        }

        if ($clinicId > 0) {
            $clinic = Clinic::query()->find($clinicId);
            if ($clinic) {
                $this->recordActivity($admin, $clinic, 'admin_impersonate.end', [
                    'token_id' => $tokenId,
                ], $targetUserId > 0 ? $targetUserId : null);
            }
        }

        session()->forget('backoffice_impersonation');
    }

    public function recentActivity(Clinic $clinic, int $limit = 50)
    {
        if (! $this->canUseClinicActivitiesTable()) {
            return collect();
        }

        return BackofficeClinicActivity::query()
            ->where('clinic_id', $clinic->id)
            ->with(['adminUser:id,name,email', 'targetUser:id,name,email'])
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    private function resolveOwner(Clinic $clinic): ?User
    {
        $owner = $clinic->ownerUser()->first();
        if ($owner) {
            return $owner;
        }

        return $clinic->users()->orderBy('id')->first();
    }

    private function recordActivity(AdminUser $admin, Clinic $clinic, string $event, array $context = [], ?int $targetUserId = null): void
    {
        if ($this->canUseClinicActivitiesTable()) {
            BackofficeClinicActivity::query()->create([
                'clinic_id' => $clinic->id,
                'admin_user_id' => $admin->id,
                'target_user_id' => $targetUserId,
                'event' => $event,
                'result' => 'success',
                'context' => $context,
                'created_at' => now(),
            ]);
        }

        Log::info($event, [
            'event' => $event,
            'result' => 'success',
            'clinic_id' => $clinic->id,
            'admin_user_id' => $admin->id,
            'target_user_id' => $targetUserId,
            'context' => $context,
        ]);
    }

    private function canUseClinicActivitiesTable(): bool
    {
        if ($this->hasClinicActivitiesTable === null) {
            $this->hasClinicActivitiesTable = Schema::hasTable('backoffice_clinic_activities');
        }

        return $this->hasClinicActivitiesTable;
    }
}
