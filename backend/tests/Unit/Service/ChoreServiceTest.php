<?php

declare(strict_types = 1);

namespace App\Tests\Unit\Service;

use App\Entity\Chore;
use App\Enum\ScheduleType;
use App\Repository\ChoreRepository;
use App\Service\ChoreService;
use App\Service\Time\AppTimezone;
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

        $service = new ChoreService($em, $repository, $clock, new AppTimezone('+05:00', 'Asia/Almaty'));

        $result = $service->markChoreDone(Uuid::v7());

        // Almaty "today" is Jun 2, so +7 days = Jun 9 (NOT Jun 8 from UTC).
        static::assertSame('2026-06-09', $result->getNextDueAt()->format('Y-m-d'));
        static::assertSame('Asia/Almaty', $result->getNextDueAt()->getTimezone()->getName());
    }
}
