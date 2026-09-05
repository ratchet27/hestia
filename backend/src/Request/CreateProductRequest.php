<?php

declare(strict_types = 1);

namespace App\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateProductRequest
{
    // @mago-ignore lint:excessive-parameter-list
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public string $name,

        #[Assert\NotBlank]
        #[Assert\Uuid]
        public string $categoryId,

        #[Assert\NotBlank]
        #[Assert\Uuid]
        public string $defaultLocationId,

        #[Assert\Positive]
        public ?int $defaultExpiryDays = null,

        #[Assert\PositiveOrZero]
        public int $minStock = 0,

        #[Assert\Length(max: 50)]
        public string $unit = 'piece',

        public bool $active = true,

        /** @var string[]|null */
        #[Assert\All([
            new Assert\Type('string'),
            new Assert\Length(max: 50)
        ])]
        #[Assert\Unique(message: 'Each barcode may be listed only once.')]
        public ?array $barcodes = null
    ) {
    }
}
