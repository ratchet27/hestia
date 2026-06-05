<?php

declare(strict_types = 1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'recipe_ingredients')]
#[ORM\Index(name: 'recipe_ingredient_recipe_idx', columns: ['recipe_id'])]
#[ORM\Index(name: 'recipe_ingredient_product_idx', columns: ['product_id'])]
#[ORM\UniqueConstraint(name: 'recipe_ingredient_unique', columns: ['recipe_id', 'product_id'])]
class RecipeIngredient
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Recipe::class, inversedBy: 'ingredients')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Recipe $recipe;

    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Product $product;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 1])]
    private int $requiredCount = 1;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    private bool $consumeOnCook = true;

    public function __construct()
    {
        $this->id = Uuid::v7();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getRecipe(): Recipe
    {
        return $this->recipe;
    }

    public function setRecipe(Recipe $recipe): static
    {
        $this->recipe = $recipe;

        return $this;
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

    public function getRequiredCount(): int
    {
        return $this->requiredCount;
    }

    public function setRequiredCount(int $requiredCount): static
    {
        $this->requiredCount = $requiredCount;

        return $this;
    }

    public function isConsumeOnCook(): bool
    {
        return $this->consumeOnCook;
    }

    // @mago-ignore lint:no-boolean-flag-parameter
    public function setConsumeOnCook(bool $consumeOnCook): static
    {
        $this->consumeOnCook = $consumeOnCook;

        return $this;
    }
}
