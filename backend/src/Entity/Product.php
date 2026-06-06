<?php

declare(strict_types = 1);

namespace App\Entity;

use App\Repository\ProductRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
#[ORM\Table(name: 'products')]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['name'], message: 'Product with this name already exists')]
// @mago-ignore lint:too-many-properties
class Product
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 255, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $name;

    #[ORM\ManyToOne(targetEntity: Category::class, inversedBy: 'products')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private Category $category;

    #[ORM\ManyToOne(targetEntity: Location::class, inversedBy: 'products')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private Location $defaultLocation;

    #[ORM\Column(nullable: true)]
    #[Assert\Positive]
    private ?int $defaultExpiryDays = null;

    #[ORM\Column(options: ['default' => 0])]
    #[Assert\PositiveOrZero]
    private int $minStock = 0;

    #[ORM\Column(length: 50, options: ['default' => 'piece'])]
    #[Assert\Length(max: 50)]
    private string $unit = 'piece';

    #[ORM\Column(options: ['default' => true])]
    private bool $active = true;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    /** @var Collection<int, Barcode> */
    #[ORM\OneToMany(
        targetEntity: Barcode::class,
        mappedBy: 'product',
        cascade: ['persist', 'remove'],
        orphanRemoval: true
    )]
    private Collection $barcodes;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->barcodes = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getCategory(): Category
    {
        return $this->category;
    }

    public function setCategory(Category $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getDefaultLocation(): Location
    {
        return $this->defaultLocation;
    }

    public function setDefaultLocation(Location $defaultLocation): static
    {
        $this->defaultLocation = $defaultLocation;

        return $this;
    }

    public function getDefaultExpiryDays(): ?int
    {
        return $this->defaultExpiryDays;
    }

    public function setDefaultExpiryDays(?int $defaultExpiryDays): static
    {
        $this->defaultExpiryDays = $defaultExpiryDays;

        return $this;
    }

    public function getMinStock(): int
    {
        return $this->minStock;
    }

    public function setMinStock(int $minStock): static
    {
        $this->minStock = $minStock;

        return $this;
    }

    public function getUnit(): string
    {
        return $this->unit;
    }

    public function setUnit(string $unit): static
    {
        $this->unit = $unit;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    // @mago-ignore lint:no-boolean-flag-parameter
    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }

    public function activate(): static
    {
        $this->active = true;

        return $this;
    }

    public function deactivate(): static
    {
        $this->active = false;

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

    /** @return Collection<int, Barcode> */
    public function getBarcodes(): Collection
    {
        return $this->barcodes;
    }

    public function addBarcode(Barcode $barcode): static
    {
        if (!$this->barcodes->contains($barcode)) {
            $this->barcodes->add($barcode);
            $barcode->setProduct($this);
        }

        return $this;
    }

    public function removeBarcode(Barcode $barcode): static
    {
        // orphanRemoval handles detach/delete; Barcode::product is non-nullable,
        // so there is no inverse side to clear here.
        $this->barcodes->removeElement($barcode);

        return $this;
    }
}
