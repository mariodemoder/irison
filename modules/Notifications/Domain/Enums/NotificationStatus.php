<?php

declare(strict_types=1);

namespace Modules\Notifications\Domain\Enums;

enum NotificationStatus: string
{
    case Queued = 'queued';
    case Sent = 'sent';
    case Failed = 'failed';
}
