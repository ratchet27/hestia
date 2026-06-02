<?php

declare(strict_types = 1);

namespace App\Factory;

use App\Entity\User;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Zenstruck\Foundry\Object\Instantiator;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<User>
 */
final class UserFactory extends PersistentObjectFactory
{
    public function __construct(
        private readonly UserPasswordHasherInterface $hasher
    ) {
        parent::__construct();
    }

    public static function class(): string
    {
        return User::class;
    }

    /** @return array<string, mixed> */
    protected function defaults(): array
    {
        return [
            'username' => self::faker()->unique()->userName(),
            'name' => self::faker()->firstName(),
            'email' => self::faker()->optional()->safeEmail(),
            // @mago-ignore lint:no-literal-password
            'plainPassword' => 'password'
        ];
    }

    #[\Override]
    protected function initialize(): static
    {
        return $this->instantiateWith(Instantiator::withConstructor()->allowExtra(
            'plainPassword'
        ))->afterInstantiate(function (User $user, array $attributes): void {
            $user->setPassword($this->hasher->hashPassword($user, $attributes['plainPassword'] ?? 'password'));
        });
    }
}
