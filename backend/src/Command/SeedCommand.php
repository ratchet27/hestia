<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Category;
use App\Entity\Location;
use App\Repository\CategoryRepository;
use App\Repository\LocationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed',
    description: 'Seeds the database with default data (idempotent)',
)]
class SeedCommand extends Command
{
    /** @var string[] */
    private const array CATEGORIES = [
        'Молочные',
        'Хлеб',
        'Мясо',
        'Крупы',
        'Консервы',
        'Гигиена',
        'Овощи',
        'Фрукты',
        'Напитки',
    ];

    /** @var string[] */
    private const array LOCATIONS = [
        'Холодильник',
        'Кладовая',
        'Ванная',
        'Другое',
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CategoryRepository $categoryRepository,
        private readonly LocationRepository $locationRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

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
