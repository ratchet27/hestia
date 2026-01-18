<?php

declare(strict_types = 1);

namespace App\Repository;

use App\Entity\Stock;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Stock>
 */
class StockRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Stock::class);
    }

    public function findByProductAndLocation(Uuid $productId, Uuid $locationId): ?Stock
    {
        // @mago-ignore analysis:mixed-return-statement
        return $this
            ->createQueryBuilder('s')
            ->andWhere('s.product = :productId')
            ->andWhere('s.location = :locationId')
            ->setParameter('productId', $productId)
            ->setParameter('locationId', $locationId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return Stock[]
     */
    // @mago-ignore lint:no-boolean-flag-parameter
    public function findByFilters(?Uuid $locationId, bool $lowStockOnly): array
    {
        $qb = $this
            ->createQueryBuilder('s')
            ->join('s.product', 'p')
            ->join('s.location', 'l')
            ->addSelect('p', 'l');

        if ($locationId !== null) {
            $qb->andWhere('s.location = :locationId')->setParameter('locationId', $locationId);
        }

        if ($lowStockOnly) {
            $qb->andWhere('s.quantity < p.minStock');
        }

        // @mago-ignore analysis:mixed-return-statement
        return $qb->getQuery()->getResult();
    }

    /**
     * @return array<array{location_id: string, location_name: string, quantity: int}>
     */
    public function getStockSummaryForProduct(Uuid $productId): array
    {
        // @mago-ignore analysis:less-specific-nested-return-statement
        return $this
            ->createQueryBuilder('s')
            ->select('IDENTITY(s.location) as location_id', 'l.name as location_name', 's.quantity')
            ->join('s.location', 'l')
            ->andWhere('s.product = :productId')
            ->setParameter('productId', $productId)
            ->getQuery()
            ->getArrayResult();
    }
}
