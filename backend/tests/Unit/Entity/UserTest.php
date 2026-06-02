<?php

declare(strict_types = 1);

namespace App\Tests\Unit\Entity;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    public function testNewUserHasUuidAndDefaultRole(): void
    {
        $user = new User('pavel');

        static::assertNotEmpty((string) $user->getId());
        static::assertSame('pavel', $user->getUsername());
        static::assertSame('pavel', $user->getUserIdentifier());
        static::assertSame(['ROLE_USER'], $user->getRoles());
    }

    public function testRolesAlwaysIncludeRoleUser(): void
    {
        $user = new User('anna');
        $user->setRoles(['ROLE_ADMIN']);

        static::assertContains('ROLE_USER', $user->getRoles());
        static::assertContains('ROLE_ADMIN', $user->getRoles());
    }

    public function testSettersRoundTrip(): void
    {
        $user = new User('pavel');
        $user->setName('Pavel')->setEmail('p@example.com')->setPassword('hashed');

        static::assertSame('Pavel', $user->getName());
        static::assertSame('p@example.com', $user->getEmail());
        static::assertSame('hashed', $user->getPassword());
    }
}
