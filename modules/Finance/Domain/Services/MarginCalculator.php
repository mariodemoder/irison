<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Services;

/**
 * Cálculos puros de margen/beneficio (sin dependencias de persistencia).
 */
class MarginCalculator
{
    public function margin(float $revenue, float $cost): float
    {
        return round($revenue - $cost, 2);
    }

    public function marginPercentage(float $revenue, float $cost): ?float
    {
        if ($revenue <= 0) {
            return null;
        }

        return round((($revenue - $cost) / $revenue) * 100, 2);
    }

    /**
     * Coste laboral de una cita a partir de su duración y la tarifa por hora.
     */
    public function appointmentLaborCost(
        float $costPerHour,
        int $minutes,
    ): float {
        if ($costPerHour <= 0 || $minutes <= 0) {
            return 0.0;
        }

        return round(($costPerHour / 60) * $minutes, 2);
    }
}
