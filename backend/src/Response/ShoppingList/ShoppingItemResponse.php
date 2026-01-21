<?php

declare(strict_types = 1);

namespace App\Response\ShoppingList;

use App\Entity\ShoppingListItem;
use App\Enum\ShoppingListSource;
use Symfony\Component\Uid\Uuid;

// @mago-ignore lint:excessive-parameter-list
final readonly class ShoppingItemResponse
{
    public function __construct(
        public Uuid $id,
        public ?Uuid $product_id,
        public string $name,
        public int $amount,
        public ?string $note,
        public ShoppingListSource $source,
        public bool $done,
        public \DateTimeImmutable $created_at,
        public ?\DateTimeImmutable $updated_at
    ) {
    }

    public static function fromEntity(ShoppingListItem $item): self
    {
        return new self(
            id: $item->getId(),
            product_id: $item->getProduct()?->getId(),
            name: $item->getDisplayName(),
            amount: $item->getAmount(),
            note: $item->getNote(),
            source: $item->getSource(),
            done: $item->isDone(),
            created_at: $item->getCreatedAt(),
            updated_at: $item->getUpdatedAt()
        );
    }
}
