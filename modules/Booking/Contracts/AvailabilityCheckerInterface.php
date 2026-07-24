<?php

declare(strict_types=1);

namespace Modules\Booking\Contracts;

interface AvailabilityCheckerInterface
{
    /**
     * Get available time slots for a specific date.
     *
     * @return array<int, array{start: string, end: string, professional_id: int, professional_name: string}>
     */
    public function getAvailableSlots(
        int $clinicId,
        int $serviceId,
        ?int $professionalId,
        string $date
    ): array;

    /**
     * Get available dates for a month.
     *
     * @return array<int, array{date: string, has_availability: bool}>
     */
    public function getAvailableDates(
        int $clinicId,
        int $serviceId,
        ?int $professionalId,
        string $yearMonth
    ): array;
}
