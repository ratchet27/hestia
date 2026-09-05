<?php

declare(strict_types = 1);

namespace App\Tests\Functional\Command;

use App\Command\SeedCommand;
use App\Entity\Category;
use App\Entity\User;
use App\Repository\CategoryRepository;
use App\Repository\LocationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class SeedCommandTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    public function testSeedsCategoriesAndLocationsIdempotently(): void
    {
        $application = new Application(self::bootKernel());
        $tester = new CommandTester($application->find('app:seed'));

        $tester->execute([]);
        $tester->assertCommandIsSuccessful();
        $tester->execute([]);
        $tester->assertCommandIsSuccessful();

        $em = self::getContainer()->get(EntityManagerInterface::class);
        static::assertCount(count(SeedCommand::CATEGORIES), $em->getRepository(Category::class)->findAll());
        static::assertStringContainsString('0 categories created', $tester->getDisplay());
    }

    public function testRefusesTheDevUserOutsideDev(): void
    {
        $application = new Application(self::bootKernel());
        $tester = new CommandTester($application->find('app:seed'));

        $tester->execute(['--with-dev-user' => true]);

        static::assertSame(1, $tester->getStatusCode());
        static::assertStringContainsString('dev environment only', $tester->getDisplay());
        $em = self::getContainer()->get(EntityManagerInterface::class);
        static::assertNull($em->getRepository(User::class)->findOneBy(['username' => SeedCommand::DEV_USERNAME]));
    }

    public function testCreatesTheDevUserInDev(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $hasher = $container->get(UserPasswordHasherInterface::class);

        // The test kernel is not "dev"; build the command with that environment by hand.
        $command = new SeedCommand(
            $em,
            $container->get(CategoryRepository::class),
            $container->get(LocationRepository::class),
            $hasher,
            'dev'
        );
        $tester = new CommandTester($command);

        $tester->execute(['--with-dev-user' => true]);
        $tester->assertCommandIsSuccessful();
        $tester->execute(['--with-dev-user' => true]);
        $tester->assertCommandIsSuccessful();

        $users = $em->getRepository(User::class)->findBy(['username' => SeedCommand::DEV_USERNAME]);
        static::assertCount(1, $users, 'the dev user is created once');
        static::assertTrue($hasher->isPasswordValid($users[0], SeedCommand::DEV_PASSWORD));
    }
}
