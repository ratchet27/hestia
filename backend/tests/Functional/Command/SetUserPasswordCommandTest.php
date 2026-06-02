<?php

declare(strict_types = 1);

namespace App\Tests\Functional\Command;

use App\Entity\User;
use App\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class SetUserPasswordCommandTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    public function testUpdatesPasswordForExistingUser(): void
    {
        UserFactory::createOne(['username' => 'testuser']);

        $application = new Application(self::bootKernel());
        $tester = new CommandTester($application->find('app:user:set-password'));

        $tester->execute([
            'username' => 'testuser',
            '--password' => 'newpassword456'
        ]);

        $tester->assertCommandIsSuccessful();

        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $em = self::getContainer()->get('doctrine')->getManager();
        $em->clear();
        $freshUser = $em->getRepository(User::class)->findOneBy(['username' => 'testuser']);
        static::assertNotNull($freshUser);
        static::assertTrue($hasher->isPasswordValid($freshUser, 'newpassword456'));
    }

    public function testFailsForNonExistentUser(): void
    {
        $application = new Application(self::bootKernel());
        $tester = new CommandTester($application->find('app:user:set-password'));

        $tester->execute([
            'username' => 'nobody',
            '--password' => 'irrelevant'
        ]);

        static::assertSame(1, $tester->getStatusCode());
        static::assertStringContainsString('not found', $tester->getDisplay());
    }
}
