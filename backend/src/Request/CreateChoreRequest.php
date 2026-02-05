<?php

declare(strict_types = 1);

namespace App\Request;

use Symfony\Component\Validator\Constraints as Assert;

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
        #[Assert\Range(min: 1, max: 365)]
        public int $schedule_value,

        #[Assert\Length(max: 100)]
        public ?string $assignee = null
    ) {
    }
}
