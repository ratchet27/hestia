<?php

declare(strict_types = 1);

namespace App\Repository;

use App\Entity\Product;
use App\Entity\ShoppingListItem;
use App\Enum\ShoppingListSource;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ShoppingListItem>
 */
class ShoppingListItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ShoppingListItem::class);
    }

    /**
     * Find an existing item by product (for upsert logic).
     */
    public function findByProduct(Product $product): ?ShoppingListItem
    {
        // @mago-ignore analysis:mixed-return-statement
        return $this
            ->createQueryBuilder('i')
            ->where('i.product = :product')
            ->setParameter('product', $product)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find an auto-generated item by product (for auto-removal check).
     */
    public function findAutoItemByProduct(Product $product): ?ShoppingListItem
    {
        // @mago-ignore analysis:mixed-return-statement
        return $this
            ->createQueryBuilder('i')
            ->where('i.product = :product')
            ->andWhere('i.source = :source')
            ->setParameter('product', $product)
            ->setParameter('source', ShoppingListSource::AUTO)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find all items ordered by done status, then creation date.
     *
     * @return ShoppingListItem[]
     */
    public function findAllOrdered(): array
    {
        // The response reads the product name per row; fetch-join it.
        // @mago-ignore analysis:mixed-return-statement
        return $this
            ->createQueryBuilder('i')
            ->leftJoin('i.product', 'p')
            ->addSelect('p')
            ->orderBy('i.done', 'ASC')
            ->addOrderBy('i.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Delete all completed items.
     *
     * @return int Number of deleted items
     */
    public function deleteCompleted(): int
    {
        return (int) $this
            ->createQueryBuilder('i')
            ->delete()
            ->where('i.done = :done')
            ->setParameter('done', true)
            ->getQuery()
            ->execute();
    }
}
