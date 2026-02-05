<?php

declare(strict_types = 1);

namespace App\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateTaskRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public string $name,

        #[Assert\Date]
        public ?string $due_date = null,

        #[Assert\Choice(choices: ['low', 'medium', 'high'])]
        public string $priority = 'medium'
    ) {
    }
}
