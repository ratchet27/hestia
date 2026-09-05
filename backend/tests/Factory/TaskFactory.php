<?php

declare(strict_types = 1);

namespace App\Tests\Factory;

use App\Entity\Task;
use App\Enum\TaskPriority;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Task>
 */
final class TaskFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Task::class;
    }

    /** @return array<string, mixed> */
    protected function defaults(): array
    {
        return [
            'name' => self::faker()->sentence(3),
            'priority' => self::faker()->randomElement(TaskPriority::cases()),
            'dueDate' => self::faker()->optional(0.7)->dateTimeBetween('now', '+1 month'),
            'done' => false
        ];
    }
}
