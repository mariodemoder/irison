<?php

namespace App\Services\Team;

use App\Models\Clinic;
use App\Models\User;
use App\Models\Booking\BookingProfessional;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class TeamUserService
{
    public function index(array $filters, int $clinicId): array
    {
        $perPage = (int) ($filters['per_page'] ?? 15);
        $q = strtolower(trim((string) ($filters['q'] ?? '')));

        $query = User::with(['profile:id,name,slug', 'profession:id,name'])
            ->where('clinic_id', $clinicId)
            ->orderBy('role', 'desc')
            ->orderBy('name');

        if ($q !== '') {
            $like = '%' . $q . '%';
            $query->where(function ($sub) use ($like) {
                $sub->whereRaw('LOWER(name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$like]);
            });
        }

        $paginator = $query->paginate($perPage);

        return [
            'data' => $paginator->getCollection()->transform(fn ($u) => $this->map($u))->toArray(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    public function show(User $user): array
    {
        $user->load([
            'profile:id,name,slug',
            'profession:id,name',
            'schedules',
            'scheduleExceptions',
            'bookingProfessional',
        ]);

        return $this->map($user);
    }

    public function store(array $input, int $clinicId): array
    {
        $clinic = Clinic::findOrFail($clinicId);

        $data = Validator::make($input, [
            'name' => 'required|string|max:255',
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('users', 'email')
                    ->where(fn ($q) => $q->where('clinic_id', $clinicId)),
            ],
            'password' => 'required|string|min:8',
            'profile_id' => 'required|integer|exists:profiles,id',
            'profession_id' => 'nullable|integer|exists:professions,id',
            'allow_online_booking' => 'nullable|boolean',
            'allow_manage_agenda' => 'nullable|boolean',
            'schedules' => 'nullable|array',
            'schedules.*.day_of_week' => 'required|integer|between:0,6',
            'schedules.*.enabled' => 'required|boolean',
            'schedules.*.start_time' => 'nullable|string',
            'schedules.*.end_time' => 'nullable|string',
            'schedule_exceptions' => 'nullable|array',
            'schedule_exceptions.*.date' => 'required|string|regex:/^\d{4}-\d{2}-\d{2}(\.\.\d{4}-\d{2}-\d{2})?$/',
            'schedule_exceptions.*.start_time' => 'nullable|string',
            'schedule_exceptions.*.end_time' => 'nullable|string',
            'schedule_exceptions.*.reason' => 'nullable|string|max:255',
        ])->validate();

        $currentCount = User::where('clinic_id', $clinicId)->count();
        if ($currentCount >= $clinic->max_users) {
            abort(409, 'Has alcanzado el límite de usuarios permitido para tu plan.');
        }

        $user = User::create([
            'clinic_id' => $clinicId,
            'name' => trim($data['name']),
            'email' => trim($data['email']),
            'password' => Hash::make($data['password']),
            'profile_id' => $data['profile_id'],
            'profession_id' => $data['profession_id'] ?? null,
            'allow_online_booking' => $data['allow_online_booking'] ?? false,
            'allow_manage_agenda' => $data['allow_manage_agenda'] ?? false,
            'role' => 'user',
            'email_verified_at' => now(),
        ]);

        $this->syncSchedules($user, $data['schedules'] ?? null, $clinic);
        $this->syncScheduleExceptions($user, $data['schedule_exceptions'] ?? []);
        $this->syncBookingProfessional($user, $data['allow_online_booking'] ?? false, $clinicId);

        return ['status' => 201, 'payload' => $this->show($user)];
    }

    public function update(User $user, array $input, int $clinicId): array
    {
        $clinic = Clinic::findOrFail($clinicId);

        $data = Validator::make($input, [
            'name' => 'sometimes|required|string|max:255',
            'email' => [
                'sometimes', 'required', 'email', 'max:255',
                Rule::unique('users', 'email')
                    ->ignore($user->id)
                    ->where(fn ($q) => $q->where('clinic_id', $clinicId)),
            ],
            'password' => 'nullable|string|min:8',
            'profile_id' => 'sometimes|required|integer|exists:profiles,id',
            'profession_id' => 'nullable|integer|exists:professions,id',
            'allow_online_booking' => 'nullable|boolean',
            'allow_manage_agenda' => 'nullable|boolean',
            'schedules' => 'nullable|array',
            'schedules.*.day_of_week' => 'required|integer|between:0,6',
            'schedules.*.enabled' => 'required|boolean',
            'schedules.*.start_time' => 'nullable|string',
            'schedules.*.end_time' => 'nullable|string',
            'schedule_exceptions' => 'nullable|array',
            'schedule_exceptions.*.date' => 'required|string|regex:/^\d{4}-\d{2}-\d{2}(\.\.\d{4}-\d{2}-\d{2})?$/',
            'schedule_exceptions.*.start_time' => 'nullable|string',
            'schedule_exceptions.*.end_time' => 'nullable|string',
            'schedule_exceptions.*.reason' => 'nullable|string|max:255',
        ])->validate();

        $payload = [];

        foreach (['name', 'email', 'profile_id', 'profession_id', 'allow_online_booking', 'allow_manage_agenda'] as $field) {
            if (array_key_exists($field, $data)) {
                $payload[$field] = $data[$field];
            }
        }

        if (array_key_exists('password', $data) && $data['password']) {
            $payload['password'] = Hash::make($data['password']);
        }

        // Prevent changing profile of owner
        if ($user->role === 'owner' && array_key_exists('profile_id', $payload)) {
            unset($payload['profile_id']);
        }

        $user->update($payload);

        if (array_key_exists('schedules', $data)) {
            $this->syncSchedules($user, $data['schedules'], $clinic);
        }

        if (array_key_exists('schedule_exceptions', $data)) {
            $this->syncScheduleExceptions($user, $data['schedule_exceptions'] ?? []);
        }

        if (array_key_exists('allow_online_booking', $data)) {
            $this->syncBookingProfessional($user, $data['allow_online_booking'], $clinicId);
        }

        return ['status' => 200, 'payload' => $this->show($user->fresh())];
    }

    public function destroy(User $user): array
    {
        if ($user->role === 'owner') {
            return ['status' => 409, 'payload' => ['message' => 'No se puede eliminar al propietario de la clínica.']];
        }

        if ($bp = $user->bookingProfessional) {
            $bp->update(['allow_online_booking' => false]);
        }

        $user->delete();

        return ['status' => 200, 'payload' => ['message' => 'Usuario eliminado.']];
    }

    private function syncSchedules(User $user, ?array $schedules, Clinic $clinic): void
    {
        $user->schedules()->delete();

        if ($schedules !== null && count($schedules) > 0) {
            foreach ($schedules as $s) {
                $user->schedules()->create([
                    'day_of_week' => $s['day_of_week'],
                    'enabled' => $s['enabled'],
                    'start_time' => $s['start_time'] ?? null,
                    'end_time' => $s['end_time'] ?? null,
                ]);
            }
        } else {
            $defaults = $clinic->business_hours;
            if ($defaults && is_array($defaults)) {
                $dayMap = ['monday' => 1, 'tuesday' => 2, 'wednesday' => 3, 'thursday' => 4, 'friday' => 5, 'saturday' => 6, 'sunday' => 0];
                foreach ($defaults as $d) {
                    $dow = $dayMap[$d['day']] ?? null;
                    if ($dow !== null) {
                        $user->schedules()->create([
                            'day_of_week' => $dow,
                            'enabled' => (bool) ($d['enabled'] ?? false),
                            'start_time' => $d['start'] ?? null,
                            'end_time' => $d['end'] ?? null,
                        ]);
                    }
                }
            }
        }
    }

    private function syncScheduleExceptions(User $user, array $exceptions): void
    {
        $user->scheduleExceptions()->delete();

        foreach ($exceptions as $e) {
            $date = $e['date'];
            $endDate = null;

            if (str_contains($date, '..')) {
                [$date, $endDate] = explode('..', $date, 2);
            }

            $user->scheduleExceptions()->create([
                'date' => $date,
                'end_date' => $endDate,
                'start_time' => $e['start_time'] ?? null,
                'end_time' => $e['end_time'] ?? null,
                'reason' => $e['reason'] ?? null,
            ]);
        }
    }

    private function syncBookingProfessional(User $user, bool $allowOnline, int $clinicId): void
    {
        if ($allowOnline) {
            BookingProfessional::firstOrCreate(
                ['user_id' => $user->id],
                ['clinic_id' => $clinicId, 'allow_online_booking' => true]
            );
        } else {
            $bp = BookingProfessional::where('user_id', $user->id)->first();
            if ($bp) {
                $bp->update(['allow_online_booking' => false]);
            }
        }
    }

    private function map(User $u): array
    {
        return [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'role' => $u->role,
            'allow_online_booking' => (bool) $u->allow_online_booking,
            'allow_manage_agenda' => (bool) $u->allow_manage_agenda,
            'profile' => $u->profile ? ['id' => $u->profile->id, 'name' => $u->profile->name, 'slug' => $u->profile->slug] : null,
            'profession' => $u->profession ? ['id' => $u->profession->id, 'name' => $u->profession->name] : null,
            'schedules' => $u->schedules ? $u->schedules->map(fn ($s) => [
                'id' => $s->id,
                'day_of_week' => $s->day_of_week,
                'enabled' => (bool) $s->enabled,
                'start_time' => $s->start_time,
                'end_time' => $s->end_time,
            ])->values()->toArray() : [],
            'schedule_exceptions' => $u->scheduleExceptions ? $u->scheduleExceptions->map(fn ($e) => [
                'id' => $e->id,
                'date' => $e->end_date ? $e->date->format('Y-m-d') . '..' . $e->end_date->format('Y-m-d') : $e->date->format('Y-m-d'),
                'start_time' => $e->start_time,
                'end_time' => $e->end_time,
                'reason' => $e->reason,
            ])->values()->toArray() : [],
            'booking_professional_id' => $u->bookingProfessional?->id,
            'created_at' => $u->created_at,
            'updated_at' => $u->updated_at,
        ];
    }
}
