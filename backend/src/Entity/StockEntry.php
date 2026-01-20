<?php

declare(strict_types = 1);

namespace App\Entity;

use App\Repository\StockEntryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Clock\DatePoint;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: StockEntryRepository::class)]
#[ORM\Table(name: 'stock_entries')]
#[ORM\Index(name: 'stock_entry_product_idx', columns: ['product_id'])]
#[ORM\Index(name: 'stock_entry_location_idx', columns: ['location_id'])]
#[ORM\Index(name: 'stock_entry_fifo_idx', columns: ['product_id', 'location_id', 'best_before', 'created_at'])]
class StockEntry
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

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $bestBefore = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->createdAt = new DatePoint();
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

    public function getBestBefore(): ?\DateTimeImmutable
    {
        return $this->bestBefore;
    }

    public function setBestBefore(?\DateTimeImmutable $bestBefore): static
    {
        $this->bestBefore = $bestBefore;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
