<?php

declare(strict_types = 1);

namespace App\Command;

use App\Entity\Category;
use App\Entity\Location;
use App\Entity\User;
use App\Repository\CategoryRepository;
use App\Repository\LocationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:seed', description: 'Seeds the database with default data (idempotent)')]
class SeedCommand extends Command
{
    /** @var string[] */
    public const array CATEGORIES = [
        'Молочные',
        'Хлеб',
        'Мясо',
        'Крупы',
        'Консервы',
        'Гигиена',
        'Овощи',
        'Фрукты',
        'Напитки'
    ];

    /** @var string[] */
    public const array LOCATIONS = [
        'Холодильник',
        'Кладовая',
        'Ванная',
        'Другое'
    ];

    /**
     * Local development login, created by `app:seed --with-dev-user` (see
     * `make install`). Refused outside the dev environment.
     */
    public const string DEV_USERNAME = 'pavel';

    // @mago-ignore lint:no-literal-password
    public const string DEV_PASSWORD = 'hestia';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CategoryRepository $categoryRepository,
        private readonly LocationRepository $locationRepository,
        private readonly UserPasswordHasherInterface $hasher,
        #[Autowire(param: 'kernel.environment')]
        private readonly string $environment
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'with-dev-user',
            null,
            InputOption::VALUE_NONE,
            sprintf(
                'Also create the dev login "%s" / "%s" (dev environment only)',
                self::DEV_USERNAME,
                self::DEV_PASSWORD
            )
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($input->getOption('with-dev-user') === true) {
            if ($this->environment !== 'dev') {
                $io->error(sprintf(
                    '--with-dev-user is for the dev environment only (current: %s).',
                    $this->environment
                ));

                return Command::FAILURE;
            }

            $io->section('Seeding dev user');
            $this->seedDevUser($io);
        }

        $io->section('Seeding Categories');

        $categoriesCreated = $this->seedCategories($io);

        $io->section('Seeding Locations');
        $locationsCreated = $this->seedLocations($io);

        $this->em->flush();

        $io->success(sprintf(
            'Seeding complete: %d categories created, %d locations created',
            $categoriesCreated,
            $locationsCreated
        ));

        return Command::SUCCESS;
    }

    private function seedDevUser(SymfonyStyle $io): void
    {
        if ($this->em->getRepository(User::class)->findOneBy(['username' => self::DEV_USERNAME]) !== null) {
            $io->writeln(sprintf('  User "%s" already exists, skipping', self::DEV_USERNAME));

            return;
        }

        $user = new User(self::DEV_USERNAME);
        $user->setName('Pavel');
        $user->setPassword($this->hasher->hashPassword($user, self::DEV_PASSWORD));

        $this->em->persist($user);

        $io->writeln(sprintf('  Created user "%s" with password "%s"', self::DEV_USERNAME, self::DEV_PASSWORD));
    }

    private function seedCategories(SymfonyStyle $io): int
    {
        $created = 0;

        foreach (self::CATEGORIES as $name) {
            $existing = $this->categoryRepository->findOneBy(['name' => $name]);

            if ($existing !== null) {
                $io->writeln(sprintf('  Category "%s" already exists, skipping', $name));
                continue;
            }

            $category = new Category();
            $category->setName($name);
            $this->em->persist($category);
            $created++;

            $io->writeln(sprintf('  Created category: %s', $name));
        }

        return $created;
    }

    private function seedLocations(SymfonyStyle $io): int
    {
        $created = 0;

        foreach (self::LOCATIONS as $name) {
            $existing = $this->locationRepository->findOneBy(['name' => $name]);

            if ($existing !== null) {
                $io->writeln(sprintf('  Location "%s" already exists, skipping', $name));
                continue;
            }

            $location = new Location();
            $location->setName($name);
            $this->em->persist($location);
            $created++;

            $io->writeln(sprintf('  Created location: %s', $name));
        }

        return $created;
    }
}
