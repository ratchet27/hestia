<?php

declare(strict_types = 1);

namespace App\Tests\Story;

use App\Command\SeedCommand;
use App\Tests\Factory\CategoryFactory;
use App\Tests\Factory\LocationFactory;
use Zenstruck\Foundry\Attribute\AsFixture;
use Zenstruck\Foundry\Story;

#[AsFixture(name: 'main')]
/** Dev fixtures: the same default taxonomy app:seed installs, plus nothing else yet. */
final class AppStory extends Story
{
    public function build(): void
    {
        foreach (SeedCommand::CATEGORIES as $name) {
            CategoryFactory::createOne(['name' => $name]);
        }

        foreach (SeedCommand::LOCATIONS as $name) {
            LocationFactory::createOne(['name' => $name]);
        }
    }
}
