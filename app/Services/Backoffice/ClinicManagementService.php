<?php

declare(strict_types=1);

namespace App\Services\Backoffice;

use App\Models\AdminUser;
use App\Models\BackofficeClinicActivity;
use App\Models\Clinic;
use App\Models\Subscription;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ClinicManagementService
{
    private ?bool $hasClinicActivitiesTable = null;
    private array $clinicColumnExists = [];

    public function listClinics(array $filters): LengthAwarePaginator
    {
        $query = Clinic::query();
        $hasPlanColumn = $this->hasClinicColumn('plan');
        $hasSuspendedAtColumn = $this->hasClinicColumn('suspended_at');

        $sort = (string) ($filters['sort'] ?? 'id');
        $direction = strtolower((string) ($filters['direction'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        $sortable = ['id', 'name', 'email', 'slug', 'plan', 'subscription_status', 'trial_ends_at', 'last_activity_at', 'created_at'];

        if (in_array($sort, $sortable, true)) {
            $query->orderBy($sort, $direction);
        } else {
            $query->orderByDesc('id');
        }

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

        if ($plan !== '' && $hasPlanColumn) {
            $query->where('plan', $plan);
        }

        if ($status !== '') {
            $query->where(function ($statusQuery) use ($status, $hasSuspendedAtColumn): void {
                if ($status === 'suspended') {
                    if ($hasSuspendedAtColumn) {
                        $statusQuery->whereNotNull('suspended_at');
                    } else {
                        $statusQuery->whereRaw('1 = 0');
                    }

                    return;
                }

                if ($status === 'cancelled') {
                    $statusQuery->whereIn('subscription_status', ['canceled', 'cancelled']);

                    return;
                }

                if ($status === 'expired') {
                    $statusQuery->whereNotNull('trial_ends_at')
                        ->where('trial_ends_at', '<', now())
                        ->when($hasSuspendedAtColumn, static function ($query): void {
                            $query->whereNull('suspended_at');
                        })
                        ->whereNotIn('subscription_status', ['active', 'past_due']);

                    return;
                }

                $statusQuery->where('subscription_status', $status);

                if ($hasSuspendedAtColumn) {
                    $statusQuery->whereNull('suspended_at');
                }
            });
        }

        return $query->paginate(20)->withQueryString();
    }

    public function updateClinic(AdminUser $admin, Clinic $clinic, array $data): Clinic
    {
        $old = $clinic->only(['name', 'slug', 'email', 'phone', 'plan']);

        $clinic->fill($data);

        if (! empty($data['plan']) && $data['plan'] !== ($old['plan'] ?? null)) {
            $clinic->max_users = Clinic::PLAN_USER_LIMITS[$data['plan']] ?? $clinic->max_users;
        }

        $clinic->save();

        $this->recordActivity($admin, $clinic, 'clinic.updated', [
            'before' => $old,
            'after' => $clinic->only(['name', 'slug', 'email', 'phone', 'plan']),
        ]);

        return $clinic->fresh();
    }

    public function extendTrial(AdminUser $admin, Clinic $clinic, int $days, ?string $reason = null): Clinic
    {
        if (! in_array(strtolower(trim((string) $clinic->subscription_status)), ['trial', 'trial_warning'], true)) {
            throw ValidationException::withMessages([
                'action' => 'Solo se puede extender el trial cuando la clínica está en trial.',
            ]);
        }

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

        ActivityLogger::log(
            tenantId: (int) $clinic->id,
            userId: null,
            event: 'trial_extended',
            description: 'Trial extendido desde backoffice',
            metadata: [
                'days' => $days,
                'reason' => $reason,
                'admin_user_id' => (int) $admin->id,
            ],
        );

        return $clinic->fresh();
    }

    public function suspend(AdminUser $admin, Clinic $clinic, ?string $reason = null): Clinic
    {
        if ($clinic->isSuspended()) {
            throw ValidationException::withMessages([
                'action' => 'La clínica ya está suspendida.',
            ]);
        }

        $updates = [];
        if ($this->hasClinicColumn('suspended_at')) {
            $updates['suspended_at'] = now();
        }

        if ($this->hasClinicColumn('status')) {
            $updates['status'] = 'suspended';
        }

        if ($updates !== []) {
            $clinic->fill($updates);
            $clinic->save();
        }

        $this->recordActivity($admin, $clinic, 'clinic.suspended', [
            'reason' => $reason,
        ]);

        return $clinic->fresh();
    }

    public function reactivate(AdminUser $admin, Clinic $clinic, ?string $reason = null): Clinic
    {
        $subscriptionStatus = strtolower(trim((string) $clinic->subscription_status));
        $canReactivate = $clinic->isSuspended() || in_array($subscriptionStatus, ['canceled', 'cancelled', 'inactive'], true);

        if (! $canReactivate) {
            throw ValidationException::withMessages([
                'action' => 'La clínica ya está activa; no se puede reactivar.',
            ]);
        }

        $updates = [];
        if ($this->hasClinicColumn('suspended_at')) {
            $updates['suspended_at'] = null;
        }

        if ($this->hasClinicColumn('status')) {
            $updates['status'] = 'active';
        }

        if (in_array((string) $clinic->subscription_status, ['canceled', 'cancelled', 'inactive'], true)) {
            $updates['subscription_status'] = 'active';
            $updates['subscribed_at'] = now();
            
            // Si la reactivamos desde backoffice y estaba cancelada, la marcamos como provider 'fake'
            // para evitar que si el cliente intenta cancelarla de nuevo (sin haber pagado), 
            // falle intentando llamar a un ID de Stripe ya inexistente.
            $updates['subscription_provider'] = 'fake';
            $updates['subscription_reference'] = 'backoffice-reactivation-' . (string) $admin->id;

            $subscription = $clinic->saasSubscriptions()->orderByDesc('id')->first();
            if (! $subscription) {
                Subscription::query()->create([
                    'clinic_id' => $clinic->id,
                    'status' => 'active',
                    'trial_ends_at' => null,
                    'current_period_end' => now()->addMonth(),
                    'stripe_subscription_id' => null,
                ]);
            } else {
                $subscription->status = 'active';
                $subscription->trial_ends_at = null;
                $subscription->current_period_end = now()->addMonth();
                $subscription->stripe_subscription_id = null; // Limpiar para evitar discrepancias
                $subscription->save();
            }
        }

        if ($updates !== []) {
            $clinic->fill($updates);
            $clinic->save();
        }

        $this->recordActivity($admin, $clinic, 'clinic.reactivated', [
            'reason' => $reason,
        ]);

        return $clinic->fresh();
    }

    public function hardDeleteFunctionalData(AdminUser $admin, Clinic $clinic): void
    {
        DB::transaction(function () use ($admin, $clinic): void {
            $clinicId = $clinic->id;

            // Desarmar FKs desde tablas preservadas (facturación Stripe)
            DB::table('subscription_requests')
                ->where('clinic_id', $clinicId)
                ->whereNotNull('requested_by')
                ->update(['requested_by' => null]);

            // Tablas hoja (sin clinic_id, subconsulta vía padre)
            DB::table('document_items')
                ->whereIn('document_id', function ($q) use ($clinicId): void {
                    $q->select('id')->from('documents')->where('clinic_id', $clinicId);
                })->delete();

            DB::table('consent_logs')
                ->whereIn('consent_id', function ($q) use ($clinicId): void {
                    $q->select('id')->from('patient_consents')->where('clinic_id', $clinicId);
                })->delete();

            DB::table('user_schedules')
                ->whereIn('user_id', function ($q) use ($clinicId): void {
                    $q->select('id')->from('users')->where('clinic_id', $clinicId);
                })->delete();

            DB::table('user_schedule_exceptions')
                ->whereIn('user_id', function ($q) use ($clinicId): void {
                    $q->select('id')->from('users')->where('clinic_id', $clinicId);
                })->delete();

            DB::table('professional_schedules')
                ->whereIn('professional_id', function ($q) use ($clinicId): void {
                    $q->select('id')->from('booking_professionals')->where('clinic_id', $clinicId);
                })->delete();

            DB::table('schedule_exceptions')
                ->whereIn('professional_id', function ($q) use ($clinicId): void {
                    $q->select('id')->from('booking_professionals')->where('clinic_id', $clinicId);
                })->delete();

            // Usuarios (cascada DB: user_schedules, user_schedule_exceptions,
            // booking_professionals → professional_schedules, schedule_exceptions)
            DB::table('users')->where('clinic_id', $clinicId)->delete();

            // Pacientes (cascada DB: appointments → reminders, clinical_records,
            // bonuses → bonus_usages, documents → document_items, patient_images,
            // patient_consents → consent_logs, credit_usages)
            DB::table('patients')->where('clinic_id', $clinicId)->delete();

            // Tablas directas restantes (sin depender de users/patients)
            $this->deleteClinicDirectModels($clinicId);

            // Activity logs (usa tenant_id, sin FK formal)
            DB::table('activity_logs')->where('tenant_id', $clinicId)->delete();

            // Sanear el registro de la clínica + marcar como deleted
            $clinic->forceFill([
                'slug' => null,
                'legal_name' => null,
                'email' => null,
                'phone' => null,
                'address' => null,
                'nif' => null,
                'locality' => null,
                'province' => null,
                'country' => null,
                'zip' => null,
                'timezone' => 'UTC',
                'business_hours' => null,
                'closed_days' => null,
                'max_users' => 1,
                'trial_ends_at' => null,
                'status' => 'inactive',
                'suspended_at' => null,
                'churned_at' => null,
                'last_activity_at' => null,
                'subscribed_at' => null,
                'invoice_background_path' => null,
                'theme_color' => null,
                'functional_data_deleted_at' => now(),
            ])->save();
        });

        ActivityLogger::log(
            tenantId: (int) $clinic->id,
            userId: null,
            event: 'hard_delete_functional_data',
            description: 'Datos funcionales eliminados desde backoffice',
            metadata: [
                'admin_user_id' => (int) $admin->id,
                'admin_email' => $admin->email ?? '',
            ],
        );
    }

    private function deleteClinicDirectModels(int $clinicId): void
    {
        DB::table('booking_pages')->where('clinic_id', $clinicId)->delete();
        DB::table('booking_services')->where('clinic_id', $clinicId)->delete();
        DB::table('consent_templates')->where('clinic_id', $clinicId)->delete();
        DB::table('consent_categories')->where('clinic_id', $clinicId)->delete();
        DB::table('bonus_types')->where('clinic_id', $clinicId)->delete();
        DB::table('appointment_types')->where('clinic_id', $clinicId)->delete();
        DB::table('products')->where('clinic_id', $clinicId)->delete();
        DB::table('counters_clinics')->where('clinic_id', $clinicId)->delete();
        DB::table('professions')->where('clinic_id', $clinicId)->delete();
        DB::table('trial_journey_events')->where('clinic_id', $clinicId)->delete();
        DB::table('backoffice_clinic_activities')->where('clinic_id', $clinicId)->delete();
    }

    public function cancelSubscription(AdminUser $admin, Clinic $clinic, ?string $reason = null): Clinic
    {
        if (in_array(strtolower(trim((string) $clinic->subscription_status)), ['canceled', 'cancelled'], true)) {
            throw ValidationException::withMessages([
                'action' => 'La clínica ya tiene la suscripción cancelada.',
            ]);
        }

        $now = now();
        $subscription = $clinic->currentSubscription();

        $graceEndsAt = $now->copy()->addDays(7);
        if ($subscription && $subscription->current_period_end && $subscription->current_period_end->isFuture()) {
            $graceEndsAt = $subscription->current_period_end->copy();
        }

        if (! $subscription) {
            $subscription = Subscription::query()->create([
                'clinic_id' => $clinic->id,
                'status' => 'canceled',
                'current_period_end' => $graceEndsAt,
            ]);
        } else {
            $subscription->status = 'canceled';
            $subscription->current_period_end = $graceEndsAt;
            $subscription->save();
        }

        $clinic->subscription_status = 'canceled';
        $clinic->subscribed_at = null;
        $clinic->save();

        $this->recordActivity($admin, $clinic, 'clinic.subscription.canceled', [
            'reason' => $reason,
            'subscription_id' => $subscription->id,
            'grace_ends_at' => $graceEndsAt->toDateTimeString(),
            'paid_days_left' => (int) max(ceil($now->diffInSeconds($graceEndsAt, false) / 86400), 0),
        ]);

        ActivityLogger::log(
            tenantId: (int) $clinic->id,
            userId: null,
            event: 'subscription_cancelled',
            description: 'Suscripcion cancelada desde backoffice',
            metadata: [
                'reason' => $reason,
                'subscription_id' => (int) $subscription->id,
                'admin_user_id' => (int) $admin->id,
            ],
        );

        return $clinic->fresh();
    }

    public function changePlan(AdminUser $admin, Clinic $clinic, string $plan, ?string $reason = null): Clinic
    {
        $beforePlan = (string) $clinic->plan;

        $clinic->plan = $plan;
        $clinic->max_users = Clinic::PLAN_USER_LIMITS[$plan] ?? $clinic->max_users;
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

    private function hasClinicColumn(string $column): bool
    {
        if (! array_key_exists($column, $this->clinicColumnExists)) {
            $this->clinicColumnExists[$column] = Schema::hasColumn('clinics', $column);
        }

        return $this->clinicColumnExists[$column];
    }
}
