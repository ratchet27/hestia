<?php

declare(strict_types = 1);

namespace App\Request;

use App\Request\Validation\ValidatesScheduleValueTrait;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateChoreRequest
{
    use ValidatesScheduleValueTrait;

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
}
