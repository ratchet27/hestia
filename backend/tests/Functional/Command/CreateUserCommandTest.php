<?php

declare(strict_types = 1);

namespace App\Tests\Functional\Command;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class CreateUserCommandTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    public function testCreatesUserWithHashedPassword(): void
    {
        $application = new Application(self::bootKernel());
        $tester = new CommandTester($application->find('app:user:create'));

        $tester->setInputs(['secret123']);
        $tester->execute([
            'username' => 'pavel',
            '--name' => 'Pavel',
            '--email' => 'p@example.com',
            '--password-stdin' => true
        ]);

        $tester->assertCommandIsSuccessful();

        $em = self::getContainer()->get('doctrine')->getManager();
        $user = $em->getRepository(User::class)->findOneBy(['username' => 'pavel']);
        static::assertNotNull($user);
        static::assertSame('Pavel', $user->getName());

        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        static::assertTrue($hasher->isPasswordValid($user, 'secret123'));
    }

    public function testPromptsForThePasswordWhenNotPiped(): void
    {
        $application = new Application(self::bootKernel());
        $tester = new CommandTester($application->find('app:user:create'));

        $tester->setInputs(['from-prompt']);
        $tester->execute(['username' => 'anna']);

        $tester->assertCommandIsSuccessful();
        static::assertStringContainsString('Password', $tester->getDisplay());

        $em = self::getContainer()->get('doctrine')->getManager();
        $user = $em->getRepository(User::class)->findOneBy(['username' => 'anna']);
        static::assertNotNull($user);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        static::assertTrue($hasher->isPasswordValid($user, 'from-prompt'));
    }

    public function testRejectsAnEmptyPipedPassword(): void
    {
        $application = new Application(self::bootKernel());
        $tester = new CommandTester($application->find('app:user:create'));

        $tester->setInputs(['']);
        $tester->execute(['username' => 'anna', '--password-stdin' => true]);

        static::assertSame(1, $tester->getStatusCode());
        static::assertStringContainsString('Password is required', $tester->getDisplay());
    }

    public function testDoesNotAcceptThePasswordAsAnOption(): void
    {
        $application = new Application(self::bootKernel());
        $tester = new CommandTester($application->find('app:user:create'));

        $this->expectException(\Symfony\Component\Console\Exception\InvalidOptionException::class);
        $tester->execute(['username' => 'anna', '--password' => 'leaks-into-shell-history']);
    }
}
