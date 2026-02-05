<?php

declare(strict_types = 1);

namespace App\Response\Chore;

use App\Entity\Chore;
use App\Enum\ScheduleType;
use Symfony\Component\ObjectMapper\Attribute\Map;
use Symfony\Component\Uid\Uuid;

#[Map(source: Chore::class)]
final readonly class ChoreResponse
{
    // @mago-ignore lint:excessive-parameter-list
    public function __construct(
        public Uuid $id,
        public string $name,
        public ScheduleType $scheduleType,
        public int $scheduleValue,
        public ?string $assignee,
        public ?\DateTimeImmutable $lastDoneAt,
        public \DateTimeImmutable $nextDueAt,
        public \DateTimeImmutable $createdAt,
        public ?\DateTimeImmutable $updatedAt
    ) {
    }
}
