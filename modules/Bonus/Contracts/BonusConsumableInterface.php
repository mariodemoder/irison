<?php

declare(strict_types=1);

namespace Modules\Bonus\Contracts;

use App\Models\Appointment;
use App\Models\Bonus;
use App\Models\BonusUsage;

interface BonusConsumableInterface
{
    /**
     * Consume a bonus session for an appointment.
     * If the bonus has session lines, decrements the matching type.
     * Otherwise, decrements the global counter (backward compatibility).
     */
    public function useBonusForAppointment(int $bonusId, Appointment $appointment, ?string $notes = null): BonusUsage;

    /**
     * Restore a bonus usage when an appointment is cancelled.
     */
    public function restoreBonusIfCancelled(Appointment $appointment): bool;
}
