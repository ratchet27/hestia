<?php

declare(strict_types = 1);

namespace App\Entity;

use App\Repository\StockRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: StockRepository::class)]
#[ORM\Table(name: 'stocks')]
#[ORM\UniqueConstraint(name: 'stock_product_location_unique', columns: ['product_id', 'location_id'])]
#[ORM\Index(name: 'stock_product_idx', columns: ['product_id'])]
#[ORM\Index(name: 'stock_location_idx', columns: ['location_id'])]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['product', 'location'], message: 'Stock entry already exists for this product and location')]
class Stock
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Product $product;

    #[ORM\ManyToOne(targetEntity: Location::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Location $location;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::INTEGER, options: ['default' => 0])]
    private int $quantity = 0;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, StockMovement> */
    #[ORM\OneToMany(targetEntity: StockMovement::class, mappedBy: 'stock', orphanRemoval: true)]
    private Collection $movements;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->movements = new ArrayCollection();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function setProduct(Product $product): static
    {
        $this->product = $product;

        return $this;
    }

    public function getLocation(): Location
    {
        return $this->location;
    }

    public function setLocation(Location $location): static
    {
        $this->location = $location;

        return $this;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): static
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /** @return Collection<int, StockMovement> */
    public function getMovements(): Collection
    {
        return $this->movements;
    }

    #[ORM\PreUpdate]
    public function updateTimestamp(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
