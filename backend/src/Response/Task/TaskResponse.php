<?php

declare(strict_types = 1);

namespace App\Response\Task;

use App\Entity\Task;
use Symfony\Component\ObjectMapper\Attribute\Map;
use Symfony\Component\Uid\Uuid;

#[Map(source: Task::class)]
final readonly class TaskResponse
{
    // @mago-ignore lint:excessive-parameter-list
    public function __construct(
        public Uuid $id,
        public string $name,
        public ?\DateTimeImmutable $dueDate,
        #[Map(source: 'priority.value')]
        public string $priority,
        #[Map(source: 'done')]
        public bool $done,
        public ?\DateTimeImmutable $doneAt,
        public \DateTimeImmutable $createdAt
    ) {
    }
}
