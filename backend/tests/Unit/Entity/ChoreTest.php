<?php

declare(strict_types = 1);

namespace App\Tests\Unit\Entity;

use App\Entity\Chore;
use App\Enum\ScheduleType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ChoreTest extends TestCase
{
    #[DataProvider('intervalScheduleProvider')]
    public function testMarkDoneWithIntervalSchedule(
        string $doneAt,
        int $intervalDays,
        string $expectedNextDue
    ): void {
        $chore = $this->createChore(ScheduleType::INTERVAL, $intervalDays);
        $doneDate = new \DateTimeImmutable($doneAt);

        $chore->markDone($doneDate);

        static::assertSame($expectedNextDue, $chore->getNextDueAt()->format('Y-m-d'));
        static::assertSame($doneAt, $chore->getLastDoneAt()->format('Y-m-d'));
    }

    /** @return iterable<string, array{string, int, string}> */
    public static function intervalScheduleProvider(): iterable
    {
        yield 'simple 7 days' => ['2026-02-05', 7, '2026-02-12'];
        yield 'cross month boundary' => ['2026-02-27', 7, '2026-03-06'];
        yield 'cross year boundary' => ['2026-12-28', 7, '2027-01-04'];
        yield '1 day interval' => ['2026-02-28', 1, '2026-03-01'];
        yield '30 day interval' => ['2026-01-15', 30, '2026-02-14'];
    }

    #[DataProvider('fixedWeeklyScheduleProvider')]
    public function testMarkDoneWithFixedWeeklySchedule(
        string $doneAt,
        int $targetWeekday,
        string $expectedNextDue
    ): void {
        $chore = $this->createChore(ScheduleType::FIXED_WEEKLY, $targetWeekday);
        $doneDate = new \DateTimeImmutable($doneAt);

        $chore->markDone($doneDate);

        static::assertSame($expectedNextDue, $chore->getNextDueAt()->format('Y-m-d'));
    }

    /** @return iterable<string, array{string, int, string}> */
    public static function fixedWeeklyScheduleProvider(): iterable
    {
        // 2026-02-05 is Thursday (4)
        yield 'done Thursday, target Friday' => ['2026-02-05', 5, '2026-02-06'];
        yield 'done Thursday, target Monday' => ['2026-02-05', 1, '2026-02-09'];
        yield 'done Thursday, target Thursday (same day)' => ['2026-02-05', 4, '2026-02-12'];
        yield 'done Thursday, target Wednesday (yesterday)' => ['2026-02-05', 3, '2026-02-11'];
        yield 'done Sunday, target Monday' => ['2026-02-08', 1, '2026-02-09'];
        yield 'cross year boundary' => ['2026-12-30', 1, '2027-01-04']; // Wed -> next Mon (5 days)
    }

    #[DataProvider('fixedMonthlyScheduleProvider')]
    public function testMarkDoneWithFixedMonthlySchedule(
        string $doneAt,
        int $targetDay,
        string $expectedNextDue
    ): void {
        $chore = $this->createChore(ScheduleType::FIXED_MONTHLY, $targetDay);
        $doneDate = new \DateTimeImmutable($doneAt);

        $chore->markDone($doneDate);

        static::assertSame($expectedNextDue, $chore->getNextDueAt()->format('Y-m-d'));
    }

    /** @return iterable<string, array{string, int, string}> */
    public static function fixedMonthlyScheduleProvider(): iterable
    {
        yield 'target day later in month' => ['2026-02-05', 15, '2026-02-15'];
        yield 'target day earlier in month' => ['2026-02-15', 5, '2026-03-05'];
        yield 'target same day' => ['2026-02-15', 15, '2026-03-15'];
        yield 'cross year boundary' => ['2026-12-20', 10, '2027-01-10'];

        // Days beyond a month's length clamp to the last valid day (no overflow/skip).
        yield 'day 31 clamps to end of February' => ['2026-02-05', 31, '2026-02-28'];
        yield 'day 31 clamps to end of April' => ['2026-04-05', 31, '2026-04-30'];
        yield 'day 30 clamps to end of February' => ['2026-02-05', 30, '2026-02-28'];
        yield 'done on the 31st does not skip February' => ['2026-01-31', 31, '2026-02-28'];
    }

    public function testMarkDoneUpdatesLastDoneAt(): void
    {
        $chore = $this->createChore(ScheduleType::INTERVAL, 7);
        static::assertNull($chore->getLastDoneAt());

        $now = new \DateTimeImmutable('2026-02-05 14:30:00');
        $chore->markDone($now);

        $lastDoneAt = $chore->getLastDoneAt();
        // @phpstan-ignore staticMethod.impossibleType (markDone sets lastDoneAt)
        static::assertNotNull($lastDoneAt);
        static::assertSame('2026-02-05 14:30:00', $lastDoneAt->format('Y-m-d H:i:s'));
    }

    public function testMarkDoneCanBeCalledMultipleTimes(): void
    {
        $chore = $this->createChore(ScheduleType::INTERVAL, 7);

        $chore->markDone(new \DateTimeImmutable('2026-02-01'));
        static::assertSame('2026-02-08', $chore->getNextDueAt()->format('Y-m-d'));

        $chore->markDone(new \DateTimeImmutable('2026-02-05'));
        static::assertSame('2026-02-12', $chore->getNextDueAt()->format('Y-m-d'));
        static::assertSame('2026-02-05', $chore->getLastDoneAt()->format('Y-m-d'));
    }

    public function testInitializeNextDueAtUsesGivenInstant(): void
    {
        $chore = $this->createChore(ScheduleType::INTERVAL, 7);

        $chore->initializeNextDueAt(new \DateTimeImmutable('2026-06-02 00:00:00'));

        static::assertSame('2026-06-09', $chore->getNextDueAt()->format('Y-m-d'));
    }

    private function createChore(ScheduleType $type, int $value): Chore
    {
        $chore = new Chore();
        $chore->setName('Test Chore');
        $chore->setScheduleType($type);
        $chore->setScheduleValue($value);

        return $chore;
    }
}
