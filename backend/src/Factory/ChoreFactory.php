<?php

declare(strict_types = 1);

namespace App\Factory;

use App\Entity\Chore;
use App\Enum\ScheduleType;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Chore>
 */
final class ChoreFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Chore::class;
    }

    /** @return array<string, mixed> */
    protected function defaults(): array
    {
        return [
            'name' => self::faker()->sentence(3),
            'scheduleType' => self::faker()->randomElement(ScheduleType::cases()),
            'scheduleValue' => self::faker()->numberBetween(1, 30),
            'assignee' => self::faker()->optional(0.5)->firstName(),
            'nextDueAt' => \DateTimeImmutable::createFromMutable(self::faker()->dateTimeBetween('now', '+1 month'))
        ];
    }
}
