<?php

declare(strict_types = 1);

namespace App\Tests\Unit\Entity;

use App\Entity\Task;
use App\Enum\TaskPriority;
use PHPUnit\Framework\TestCase;

class TaskTest extends TestCase
{
    public function testNewTaskHasDefaults(): void
    {
        $task = new Task();

        static::assertFalse($task->isDone());
        static::assertNull($task->getDoneAt());
        static::assertSame(TaskPriority::MEDIUM, $task->getPriority());
        static::assertNull($task->getDueDate());
    }

    public function testSetDoneToTrueSetsTimestamp(): void
    {
        $task = new Task();
        $task->setName('Test');

        $before = new \DateTimeImmutable();
        $task->setDone(true);
        $after = new \DateTimeImmutable();

        static::assertTrue($task->isDone());
        static::assertNotNull($task->getDoneAt());
        static::assertGreaterThanOrEqual($before, $task->getDoneAt());
        static::assertLessThanOrEqual($after, $task->getDoneAt());
    }

    public function testSetDoneToFalseClearsTimestamp(): void
    {
        $task = new Task();
        $task->setName('Test');
        $task->setDone(true);

        static::assertNotNull($task->getDoneAt());

        $task->setDone(false);

        static::assertFalse($task->isDone());
        static::assertNull($task->getDoneAt());
    }

    public function testSetDoneTrueTwiceKeepsOriginalTimestamp(): void
    {
        $task = new Task();
        $task->setName('Test');

        $task->setDone(true);

        $firstTimestamp = $task->getDoneAt();

        // Small delay to ensure timestamps would differ
        usleep(1000);

        $task->setDone(true);
        $secondTimestamp = $task->getDoneAt();

        static::assertSame($firstTimestamp, $secondTimestamp);
    }

    public function testToggleDone(): void
    {
        $task = new Task();
        $task->setName('Test');

        static::assertFalse($task->isDone());

        // @phpstan-ignore booleanNot.alwaysTrue (testing toggle behavior)
        $task->setDone(!$task->isDone());
        static::assertTrue($task->isDone());
        static::assertNotNull($task->getDoneAt());

        $task->setDone(!$task->isDone());
        static::assertFalse($task->isDone());
        static::assertNull($task->getDoneAt());
    }

    public function testPriorityCanBeChanged(): void
    {
        $task = new Task();
        $task->setName('Test');

        static::assertSame(TaskPriority::MEDIUM, $task->getPriority());

        $task->setPriority(TaskPriority::HIGH);
        static::assertSame(TaskPriority::HIGH, $task->getPriority());

        $task->setPriority(TaskPriority::LOW);
        static::assertSame(TaskPriority::LOW, $task->getPriority());
    }

    public function testDueDateCanBeSetAndCleared(): void
    {
        $task = new Task();
        $task->setName('Test');

        static::assertNull($task->getDueDate());

        $dueDate = new \DateTimeImmutable('2026-02-15');
        $task->setDueDate($dueDate);
        $retrievedDate = $task->getDueDate();
        // @phpstan-ignore staticMethod.impossibleType (we just set the due date)
        static::assertNotNull($retrievedDate);
        static::assertSame('2026-02-15', $retrievedDate->format('Y-m-d'));

        $task->setDueDate(null);
        static::assertNull($task->getDueDate());
    }
}
