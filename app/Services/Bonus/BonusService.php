<?php
declare(strict_types=1);

namespace App\Services\Bonus;

use App\Models\Bonus;
use App\Models\BonusUsage;
use App\Models\Appointment;
use App\Models\Payment;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use DomainException;

class BonusService
{
    public function index(array $filters, ?int $clinicId = null): array
    {
        $perPage = (int) ($filters['per_page'] ?? 15);
        $perPage = max(1, min($perPage, 100));
        $q = trim((string) ($filters['q'] ?? ''));
        $paymentState = trim((string) ($filters['payment_state'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));

        $query = $this->queryWithPaymentFlag($clinicId)
            ->with('patient')
            ->when($clinicId, function (Builder $builder) use ($clinicId) {
                $builder->where('bonuses.clinic_id', $clinicId);
            });

        if ($q !== '') {
            $like = '%' . strtolower($q) . '%';
            $query->where(function (Builder $sub) use ($like) {
                $sub->whereRaw('LOWER(bonuses.name) LIKE ?', [$like])
                    ->orWhereHas('patient', function (Builder $patientQuery) use ($like) {
                        $patientQuery->whereRaw("LOWER(CONCAT(first_name, ' ', last_name)) LIKE ?", [$like])
                            ->orWhereRaw('LOWER(nif) LIKE ?', [$like]);
                    });
            });
        }

        if ($paymentState === 'paid') {
            $query->whereNotNull('paid_packages.package_id');
        } elseif ($paymentState === 'unpaid') {
            $query->whereNull('paid_packages.package_id');
        }

        if (in_array($status, ['active', 'last', 'exhausted', 'expired'], true)) {
            $today = now()->toDateString();

            if ($status === 'expired') {
                $query->whereNotNull('bonuses.expires_at')
                    ->whereDate('bonuses.expires_at', '<', $today);
            }

            if ($status === 'active') {
                $query->where('bonuses.remaining_sessions', '>', 1)
                    ->where(function (Builder $statusQuery) use ($today) {
                        $statusQuery->whereNull('bonuses.expires_at')
                            ->orWhereDate('bonuses.expires_at', '>=', $today);
                    });
            }

            if ($status === 'last') {
                $query->where('bonuses.remaining_sessions', '=', 1)
                    ->where(function (Builder $statusQuery) use ($today) {
                        $statusQuery->whereNull('bonuses.expires_at')
                            ->orWhereDate('bonuses.expires_at', '>=', $today);
                    });
            }

            if ($status === 'exhausted') {
                $query->where('bonuses.remaining_sessions', '<=', 0)
                    ->where(function (Builder $statusQuery) use ($today) {
                        $statusQuery->whereNull('bonuses.expires_at')
                            ->orWhereDate('bonuses.expires_at', '>=', $today);
                    });
            }
        }

        $summaryQuery = clone $query;

        $summary = [
            'count' => (int) $summaryQuery->count('bonuses.id'),
            'total_amount' => (float) $summaryQuery->sum('bonuses.price'),
        ];

        $paginator = $query
            ->orderBy('bonuses.created_at', 'desc')
            ->paginate($perPage);

        return [
            'data' => $this->mapPaginatorItems($paginator),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'summary' => $summary,
        ];
    }

    public function packageCandidatesForPatient(
        int $patientId,
        int $clinicId,
        bool $onlyUnpaid = true,
        ?int $currentBonusId = null
    ): Collection {
        $query = $this->queryWithPaymentFlag($clinicId)
            ->where('bonuses.clinic_id', $clinicId)
            ->where('bonuses.patient_id', $patientId)
            ->when($onlyUnpaid, function (Builder $builder) use ($currentBonusId) {
                $builder->where(function (Builder $subQuery) use ($currentBonusId) {
                    $subQuery->whereNull('paid_packages.package_id');

                    if ($currentBonusId) {
                        $subQuery->orWhere('bonuses.id', (int) $currentBonusId);
                    }
                });
            })
            ->orderByDesc('bonuses.created_at');

        return $query->get();
    }

    public function forPatient(int $patientId, ?int $clinicId = null, bool $activeOnly = false): Collection
    {
        $query = $this->queryWithPaymentFlag($clinicId)
            ->where('bonuses.patient_id', $patientId);

        if ($clinicId) {
            $query->where('bonuses.clinic_id', $clinicId);
        }

        if ($activeOnly) {
            $query->where('remaining_sessions', '>', 0)
                ->where(function ($query) {
                    $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
                });
        }

        $list = $query->orderBy('created_at', 'desc')->get();

        return $list->map(function (Bonus $bonus) {
            $isPaid = !is_null($bonus->getAttribute('package_payment_id'));

            return [
                'id' => $bonus->id,
                'counter' => $bonus->counter,
                'name' => $bonus->name,
                'total_sessions' => (int) $bonus->total_sessions,
                'remaining_sessions' => (int) $bonus->remaining_sessions,
                    'price' => (float) ($bonus->price ?? 0),
                    'invoice_id' => $bonus->invoice_id ? (int) $bonus->invoice_id : null,
                'expires_at' => $bonus->expires_at ? $bonus->expires_at->toDateString() : null,
                'status' => $bonus->status,
                'is_paid' => $isPaid,
            ];
        });
    }

    public function unpaidCount(?int $clinicId = null): int
    {
        return (int) $this->queryWithPaymentFlag($clinicId)
            ->whereNull('paid_packages.package_id')
            ->count('bonuses.id');
    }

    public function createForPatient(int $patientId, array $data, ?int $clinicId = null): Bonus
    {
        if (!$clinicId) {
            throw new DomainException('No se encontró clínica activa para crear el bono');
        }

        return $this->assignBonusToPatient(
            $clinicId,
            $patientId,
            $data['name'],
            (int) $data['total_sessions'],
            (float) ($data['price'] ?? 0),
            isset($data['expires_at']) ? new \DateTime($data['expires_at']) : null
        );
    }

    public function updateBonus(Bonus $bonus, array $data): Bonus
    {
        $bonus->fill($data);
        $bonus->save();

        return $bonus;
    }

    public function deleteBonus(Bonus $bonus): void
    {
        if (!empty($bonus->invoice_id)) {
            throw new DomainException('No se puede eliminar un bono que ya está facturado');
        }

        $bonus->delete();
    }

    public function expiring(?int $clinicId = null): Collection
    {
        $query = Bonus::with('patient')
            ->where('remaining_sessions', 1)
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });

        if ($clinicId) {
            $query->where('clinic_id', $clinicId);
        }

        $list = $query->orderBy('remaining_sessions', 'asc')
            ->orderBy('updated_at', 'desc')
            ->get();

        return $list->map(function (Bonus $bonus) {
            return [
                'id' => $bonus->patient ? $bonus->patient->id : null,
                'patient_name' => $bonus->patient ? $bonus->patient->name : '—',
                'sessions_left' => (int) $bonus->remaining_sessions,
                'bonus_id' => $bonus->id,
                'bonus_name' => $bonus->name ?? null,
                'expires_at' => $bonus->expires_at ? $bonus->expires_at->toDateString() : null,
            ];
        })->filter(function (array $item) {
            return $item['id'] !== null;
        })->values();
    }

    /**
     * Assign a bonus to a patient (create Bonus record).
     * Returns the created Bonus.
     */
    public function assignBonusToPatient(int $clinicId, int $patientId, string $name, int $totalSessions, float $price = 0.0, ?\DateTime $expiresAt = null): Bonus
    {
        $data = [
            'clinic_id' => $clinicId,
            'patient_id' => $patientId,
            'name' => $name,
            'total_sessions' => $totalSessions,
            'remaining_sessions' => $totalSessions,
            'price' => $price,
            'expires_at' => $expiresAt,
        ];

        return Bonus::create($data);
    }

    /**
     * Use a bonus for an appointment.
     * Throws Exception on invalid usage (no remaining, expired, already used for this appointment).
     * Returns the created BonusUsage.
     */
    public function useBonusForAppointment(int $bonusId, Appointment $appointment, ?string $notes = null): BonusUsage
    {
        return DB::transaction(function () use ($bonusId, $appointment, $notes) {
            $bonus = Bonus::where('id', $bonusId)->lockForUpdate()->first();
            if (!$bonus) {
                throw new \Exception('Bono no encontrado');
            }

            // Check expiration (inclusive of the expiration day)
            if ($bonus->isExpired()) {
                throw new \Exception('Bono expirado');
            }

            // Check remaining
            if ($bonus->remaining_sessions <= 0) {
                throw new \Exception('El bono seleccionado no está disponible');
            }

            // Prevent double use for same appointment
            $existing = BonusUsage::where('bonus_id', $bonus->id)->where('appointment_id', $appointment->id)->first();
            if ($existing) {
                throw new \Exception('El bono ya fue usado para esta cita');
            }

            // Decrement and create usage
            $bonus->remaining_sessions = $bonus->remaining_sessions - 1;
            $bonus->save();

            $usage = BonusUsage::create([
                'bonus_id' => $bonus->id,
                'appointment_id' => $appointment->id,
                'used_at' => now(),
                'notes' => $notes,
            ]);

            return $usage;
        });
    }

    /**
     * Restore a bonus usage when an appointment is cancelled.
     * If a usage exists for the appointment, increments remaining_sessions and deletes usage.
     * Returns true if restored, false if no usage found.
     */
    public function restoreBonusIfCancelled(Appointment $appointment): bool
    {
        return DB::transaction(function () use ($appointment) {
            $usage = BonusUsage::where('appointment_id', $appointment->id)->first();
            if (!$usage) return false;

            $bonus = Bonus::where('id', $usage->bonus_id)->lockForUpdate()->first();
            if (!$bonus) {
                // If bonus no longer exists, just delete usage
                $usage->delete();
                return true;
            }

            $bonus->remaining_sessions = $bonus->remaining_sessions + 1;
            $bonus->save();

            $usage->delete();

            return true;
        });
    }

    private function queryWithPaymentFlag(?int $clinicId = null): Builder
    {
        $paidPackages = Payment::query()
            ->select('package_id')
            ->whereNotNull('package_id')
            ->when($clinicId, function (Builder $query) use ($clinicId) {
                $query->where('clinic_id', $clinicId);
            })
            ->groupBy('package_id');

        return Bonus::query()
            ->select('bonuses.*')
            ->addSelect(DB::raw('paid_packages.package_id as package_payment_id'))
            ->leftJoinSub($paidPackages, 'paid_packages', function ($join) {
                $join->on('paid_packages.package_id', '=', 'bonuses.id');
            });
    }

    private function mapPaginatorItems(LengthAwarePaginator $paginator): array
    {
        return $paginator->getCollection()->transform(function (Bonus $bonus) {
            $isPaid = !is_null($bonus->getAttribute('package_payment_id'));

            return [
                'id' => $bonus->id,
                'counter' => $bonus->counter,
                'clinic_id' => $bonus->clinic_id,
                'patient_id' => $bonus->patient_id,
                'name' => $bonus->name,
                'total_sessions' => (int) ($bonus->total_sessions ?? 0),
                'remaining_sessions' => (int) ($bonus->remaining_sessions ?? 0),
                'price' => (float) ($bonus->price ?? 0),
                'expires_at' => $bonus->expires_at,
                'status' => $bonus->status,
                'is_paid' => $isPaid,
                    'invoice_id' => $bonus->invoice_id ? (int) $bonus->invoice_id : null,
                'created_at' => $bonus->created_at,
                'updated_at' => $bonus->updated_at,
                'patient' => $bonus->patient ? [
                    'id' => $bonus->patient->id,
                    'counter' => $bonus->patient->counter,
                    'name' => $bonus->patient->name,
                    'nif' => $bonus->patient->nif,
                ] : null,
            ];
        })->toArray();
    }
}
