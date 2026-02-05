<?php

declare(strict_types = 1);

namespace App\Enum;

enum ScheduleType: string
{
    case INTERVAL = 'interval';
    case FIXED_WEEKLY = 'fixed_weekly';
    case FIXED_MONTHLY = 'fixed_monthly';
}
