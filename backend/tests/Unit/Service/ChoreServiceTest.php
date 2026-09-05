<?php

declare(strict_types = 1);

namespace App\Tests\Unit\Service;

use App\Entity\Chore;
use App\Enum\ScheduleType;
use App\Repository\ChoreRepository;
use App\Request\SaveChoreRequest;
use App\Service\ChoreService;
use App\Service\Time\AppTimezone;
use App\Service\Time\HouseholdCalendar;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Uid\Uuid;

class ChoreServiceTest extends TestCase
{
    public function testMarkChoreDoneAnchorsToAlmatyCalendarDay(): void
    {
        // 21:00 UTC on Jun 1 == 02:00 Almaty (+05) on Jun 2.
        $clock = new MockClock(new \DateTimeImmutable('2026-06-01 21:00:00', new \DateTimeZone('UTC')));

        $chore = new Chore()
            ->setName('Test')
            ->setScheduleType(ScheduleType::INTERVAL)
            ->setScheduleValue(7);

        $repository = $this->createStub(ChoreRepository::class);
        $repository->method('find')->willReturn($chore);

        $em = $this->createStub(EntityManagerInterface::class);

        $service = new ChoreService(
            $em,
            $repository,
            new HouseholdCalendar($clock, new AppTimezone('+05:00', 'Asia/Almaty'))
        );

        $result = $service->markChoreDone(Uuid::v7());

        // Almaty "today" is Jun 2, so +7 days = Jun 9 (NOT Jun 8 from UTC).
        static::assertSame('2026-06-09', $result->getNextDueAt()->format('Y-m-d'));
        static::assertSame('Asia/Almaty', $result->getNextDueAt()->getTimezone()->getName());
    }

    public function testUpdateChoreRecomputesNextDueAtFromNowWhenScheduleChanges(): void
    {
        $clock = new MockClock(new \DateTimeImmutable('2026-06-06 10:00:00', new \DateTimeZone('UTC')));

        $chore = new Chore()
            ->setName('Old name')
            ->setScheduleType(ScheduleType::INTERVAL)
            ->setScheduleValue(14);
        $chore->initializeNextDueAt(new \DateTimeImmutable('2026-01-01'));

        $service = $this->serviceWith($chore, $clock);

        $request = new SaveChoreRequest('Old name', 'interval', 2);
        $result = $service->updateChore(Uuid::v7(), $request);

        // Anchored to "now" (Jun 6) + 2 days = Jun 8, not from the stale Jan next-due.
        static::assertSame('2026-06-08', $result->getNextDueAt()->format('Y-m-d'));
    }

    public function testUpdateChoreLeavesNextDueAtUnchangedWhenOnlyNameOrAssigneeChanges(): void
    {
        $clock = new MockClock(new \DateTimeImmutable('2026-06-06 10:00:00', new \DateTimeZone('UTC')));

        $chore = new Chore()
            ->setName('Old name')
            ->setScheduleType(ScheduleType::INTERVAL)
            ->setScheduleValue(14);
        $chore->initializeNextDueAt(new \DateTimeImmutable('2026-01-01'));

        $originalNextDue = $chore->getNextDueAt()->format('Y-m-d');

        $service = $this->serviceWith($chore, $clock);

        // Same schedule, new name + assignee → next-due must not move.
        $request = new SaveChoreRequest('New name', 'interval', 14, 'Pavel');
        $result = $service->updateChore(Uuid::v7(), $request);

        static::assertSame('New name', $result->getName());
        static::assertSame('Pavel', $result->getAssignee());
        static::assertSame($originalNextDue, $result->getNextDueAt()->format('Y-m-d'));
    }

    private function serviceWith(Chore $chore, MockClock $clock): ChoreService
    {
        $repository = $this->createStub(ChoreRepository::class);
        $repository->method('find')->willReturn($chore);

        $em = $this->createStub(EntityManagerInterface::class);

        return new ChoreService(
            $em,
            $repository,
            new HouseholdCalendar($clock, new AppTimezone('+05:00', 'Asia/Almaty'))
        );
    }
}
