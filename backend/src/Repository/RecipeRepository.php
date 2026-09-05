<?php

declare(strict_types = 1);

namespace App\Repository;

use App\Entity\Recipe;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Recipe>
 */
class RecipeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Recipe::class);
    }

    /**
     * @return Recipe[]
     */
    public function findAllOrdered(): array
    {
        // Fetch-join ingredients and their products: the list response reads
        // both for every recipe, which was R x I lazy loads.
        // @mago-ignore analysis:mixed-return-statement
        return $this
            ->createQueryBuilder('r')
            ->leftJoin('r.ingredients', 'i')
            ->addSelect('i')
            ->leftJoin('i.product', 'p')
            ->addSelect('p')
            ->orderBy('r.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
