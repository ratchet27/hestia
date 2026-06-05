<?php

declare(strict_types = 1);

namespace App\Entity;

use App\Repository\RecipeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Clock\DatePoint;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: RecipeRepository::class)]
#[ORM\Table(name: 'recipes')]
class Recipe
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $instructions = null;

    #[ORM\Column(length: 1024, nullable: true)]
    private ?string $sourceUrl = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, RecipeIngredient> */
    #[ORM\OneToMany(
        targetEntity: RecipeIngredient::class,
        mappedBy: 'recipe',
        cascade: ['persist', 'remove'],
        orphanRemoval: true
    )]
    private Collection $ingredients;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->createdAt = new DatePoint();
        $this->ingredients = new ArrayCollection();
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

    public function getInstructions(): ?string
    {
        return $this->instructions;
    }

    public function setInstructions(?string $instructions): static
    {
        $this->instructions = $instructions;

        return $this;
    }

    public function getSourceUrl(): ?string
    {
        return $this->sourceUrl;
    }

    public function setSourceUrl(?string $sourceUrl): static
    {
        $this->sourceUrl = $sourceUrl;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return Collection<int, RecipeIngredient> */
    public function getIngredients(): Collection
    {
        return $this->ingredients;
    }

    public function addIngredient(RecipeIngredient $ingredient): static
    {
        if (!$this->ingredients->contains($ingredient)) {
            $this->ingredients->add($ingredient);
            $ingredient->setRecipe($this);
        }

        return $this;
    }

    public function removeIngredient(RecipeIngredient $ingredient): static
    {
        $this->ingredients->removeElement($ingredient);

        return $this;
    }

    public function clearIngredients(): static
    {
        $this->ingredients->clear();

        return $this;
    }
}
