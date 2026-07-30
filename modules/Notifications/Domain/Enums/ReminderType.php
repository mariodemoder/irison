<?php

declare(strict_types=1);

namespace Modules\Notifications\Domain\Enums;

enum ReminderType: string
{
    case TwentyFourHours = '24h';
    case TwoHours = '2h';

    public function hoursBefore(): int
    {
        return match ($this) {
            self::TwentyFourHours => 24,
            self::TwoHours => 2,
        };
    }

    public function sentAtColumn(): string
    {
        return match ($this) {
            self::TwentyFourHours => 'reminder_24h_sent_at',
            self::TwoHours => 'reminder_2h_sent_at',
        };
    }
}
