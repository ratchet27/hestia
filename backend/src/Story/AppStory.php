<?php

declare(strict_types = 1);

namespace App\Story;

use App\Factory\CategoryFactory;
use App\Factory\LocationFactory;
use Zenstruck\Foundry\Attribute\AsFixture;
use Zenstruck\Foundry\Story;

#[AsFixture(name: 'main')]
final class AppStory extends Story
{
    private const array CATEGORIES = [
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

    private const array LOCATIONS = [
        'Холодильник',
        'Кладовая',
        'Ванная',
        'Другое'
    ];

    public function build(): void
    {
        foreach (self::CATEGORIES as $name) {
            CategoryFactory::createOne(['name' => $name]);
        }

        foreach (self::LOCATIONS as $name) {
            LocationFactory::createOne(['name' => $name]);
        }
    }
}
