<?php

declare(strict_types=1);

namespace Modules\Notifications\Domain\Enums;

enum NotificationChannel: string
{
    case Email = 'email';
    case Database = 'database';
    case Sms = 'sms';
    case Push = 'push';
}
