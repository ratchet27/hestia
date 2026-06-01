<?php

declare(strict_types = 1);

namespace App\Request;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final readonly class UpdateChoreRequest
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
        $max = match ($this->schedule_type) {
            'fixed_weekly' => 7,
            'fixed_monthly' => 28,
            default => 365,
        };

        if ($this->schedule_value < 1 || $this->schedule_value > $max) {
            $context->buildViolation('schedule_value must be between 1 and {{ max }} for schedule_type "{{ type }}".')
                ->setParameter('{{ max }}', (string) $max)
                ->setParameter('{{ type }}', $this->schedule_type)
                ->atPath('schedule_value')
                ->addViolation();
        }
    }
}
