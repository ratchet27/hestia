<?php

declare(strict_types = 1);

namespace App\Response\Auth;

use App\Entity\User;
use Symfony\Component\ObjectMapper\Attribute\Map;
use Symfony\Component\Uid\Uuid;

#[Map(source: User::class)]
final readonly class UserResponse
{
    /** @param list<string> $roles */
    public function __construct(
        public Uuid $id,
        public string $username,
        public ?string $email,
        public string $name,
        public array $roles
    ) {
    }
}
