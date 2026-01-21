<?php

declare(strict_types = 1);

namespace App\Entity;

use App\Enum\ShoppingListSource;
use App\Repository\ShoppingListItemRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Clock\DatePoint;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ShoppingListItemRepository::class)]
#[ORM\Table(name: 'shopping_list_items')]
#[ORM\Index(name: 'shopping_list_product_idx', columns: ['product_id'])]
#[ORM\Index(name: 'shopping_list_source_idx', columns: ['source'])]
#[ORM\HasLifecycleCallbacks]
class ShoppingListItem
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Product $product = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $customName = null;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 1])]
    private int $amount = 1;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $note = null;

    #[ORM\Column(length: 20, enumType: ShoppingListSource::class, options: ['default' => 'manual'])]
    private ShoppingListSource $source = ShoppingListSource::MANUAL;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $done = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->createdAt = new DatePoint();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): static
    {
        $this->product = $product;

        return $this;
    }

    public function getCustomName(): ?string
    {
        return $this->customName;
    }

    public function setCustomName(?string $customName): static
    {
        $this->customName = $customName;

        return $this;
    }

    public function getDisplayName(): string
    {
        return $this->product?->getName() ?? $this->customName ?? '';
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function setAmount(int $amount): static
    {
        $this->amount = $amount;

        return $this;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): static
    {
        $this->note = $note;

        return $this;
    }

    public function getSource(): ShoppingListSource
    {
        return $this->source;
    }

    public function setSource(ShoppingListSource $source): static
    {
        $this->source = $source;

        return $this;
    }

    public function isDone(): bool
    {
        return $this->done;
    }

    // @mago-ignore lint:no-boolean-flag-parameter
    public function setDone(bool $done): static
    {
        $this->done = $done;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    #[ORM\PreUpdate]
    public function updateTimestamp(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
