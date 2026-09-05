<?php

declare(strict_types = 1);

namespace App\Request;

use Symfony\Component\Validator\Constraints as Assert;

/** Payload for both POST (create) and PUT (full update); the two verbs accept the same fields. */
final readonly class SaveTaskRequest
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
