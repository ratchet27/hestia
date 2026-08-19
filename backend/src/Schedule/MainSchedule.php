<?php

declare(strict_types = 1);

namespace App\Schedule;

use App\Message\SendDailyExpirySummary;
use App\Service\Time\AppTimezone;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

#[AsSchedule('main')]
final readonly class MainSchedule implements ScheduleProviderInterface
{
    public function __construct(
        #[Autowire(env: 'TELEGRAM_DAILY_SUMMARY_TIME')]
        private string $dailySummaryTime,
        private AppTimezone $appTimezone
    ) {
    }

    public function getSchedule(): Schedule
    {
        [$hour, $minute] = array_map(intval(...), explode(':', $this->dailySummaryTime));
        $cron = sprintf('%d %d * * *', $minute, $hour);

        return new Schedule()->add(RecurringMessage::cron(
            $cron,
            new SendDailyExpirySummary(),
            $this->appTimezone->get()
        ));
    }
}
