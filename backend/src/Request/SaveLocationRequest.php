<?php

declare(strict_types = 1);

namespace App\Request;

use Symfony\Component\Validator\Constraints as Assert;

/** Payload for both POST (create) and PUT (full update); the two verbs accept the same fields. */
final readonly class SaveLocationRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 100)]
        public string $name
    ) {
    }
}
