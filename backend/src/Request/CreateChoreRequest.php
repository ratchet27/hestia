<?php

declare(strict_types = 1);

namespace App\Request;

use App\Enum\ScheduleType;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final readonly class CreateChoreRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public string $name,

        #[Assert\NotBlank]
        #[Assert\Choice(choices: ['interval', 'fixed_weekly', 'fixed_monthly'])]
        public string $schedule_type,

        #[Assert\NotBlank]
        #[Assert\Positive]
        public int $schedule_value,

        #[Assert\Length(max: 100)]
        public ?string $assignee = null
    ) {
    }

    #[Assert\Callback]
    public function validateScheduleValue(ExecutionContextInterface $context): void
    {
        $type = ScheduleType::tryFrom($this->schedule_type);

        // Invalid schedule_type is already reported by Assert\Choice; don't add a
        // second, confusing violation here.
        if ($type === null) {
            return;
        }

        $max = match ($type) {
            ScheduleType::FIXED_WEEKLY => 7,
            ScheduleType::FIXED_MONTHLY => 28,
            ScheduleType::INTERVAL => 365
        };

        if ($this->schedule_value < 1 || $this->schedule_value > $max) {
            $context
                ->buildViolation('schedule_value must be between 1 and {{ max }} for schedule_type "{{ type }}".')
                ->setParameter('{{ max }}', (string) $max)
                ->setParameter('{{ type }}', $this->schedule_type)
                ->atPath('schedule_value')
                ->addViolation();
        }
    }
}
