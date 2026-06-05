<?php

declare(strict_types = 1);

namespace App\Repository;

use App\Entity\StockEntry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<StockEntry>
 */
class StockEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StockEntry::class);
    }

    /**
     * Find entries for FIFO consumption (earliest best_before first, NULL last, then created_at).
     *
     * @return StockEntry[]
     */
    public function findForFifoConsumption(Uuid $productId, Uuid $locationId, int $limit): array
    {
        // @mago-ignore analysis:mixed-return-statement
        return $this
            ->createQueryBuilder('e')
            ->where('e.product = :productId')
            ->andWhere('e.location = :locationId')
            ->setParameter('productId', $productId)
            ->setParameter('locationId', $locationId)
            ->orderBy('CASE WHEN e.bestBefore IS NULL THEN 1 ELSE 0 END', 'ASC')
            ->addOrderBy('e.bestBefore', 'ASC')
            ->addOrderBy('e.createdAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find entries for FIFO consumption across ALL locations (earliest best_before first, NULL last).
     *
     * @return StockEntry[]
     */
    public function findForFifoConsumptionAcrossLocations(Uuid $productId, int $limit): array
    {
        // @mago-ignore analysis:mixed-return-statement
        return $this
            ->createQueryBuilder('e')
            ->where('e.product = :productId')
            ->setParameter('productId', $productId)
            ->orderBy('CASE WHEN e.bestBefore IS NULL THEN 1 ELSE 0 END', 'ASC')
            ->addOrderBy('e.bestBefore', 'ASC')
            ->addOrderBy('e.createdAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Count entries for a product at a location.
     */
    public function countByProductAndLocation(Uuid $productId, Uuid $locationId): int
    {
        return (int) $this
            ->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where('e.product = :productId')
            ->andWhere('e.location = :locationId')
            ->setParameter('productId', $productId)
            ->setParameter('locationId', $locationId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count total entries for a product across all locations.
     */
    public function countByProduct(Uuid $productId): int
    {
        return (int) $this
            ->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where('e.product = :productId')
            ->setParameter('productId', $productId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Find entries by location.
     *
     * @return StockEntry[]
     */
    public function findByLocation(Uuid $locationId): array
    {
        // @mago-ignore analysis:mixed-return-statement
        return $this
            ->createQueryBuilder('e')
            ->where('e.location = :locationId')
            ->setParameter('locationId', $locationId)
            ->orderBy('e.bestBefore', 'ASC')
            ->addOrderBy('e.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find entries by product.
     *
     * @return StockEntry[]
     */
    public function findByProduct(Uuid $productId): array
    {
        // @mago-ignore analysis:mixed-return-statement
        return $this
            ->createQueryBuilder('e')
            ->where('e.product = :productId')
            ->setParameter('productId', $productId)
            ->orderBy('e.bestBefore', 'ASC')
            ->addOrderBy('e.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find entries expiring up to and including the given cutoff date (includes already expired, ordered by urgency).
     *
     * @return StockEntry[]
     */
    public function findExpiring(\DateTimeImmutable $cutoff): array
    {
        // @mago-ignore analysis:mixed-return-statement
        return $this
            ->createQueryBuilder('e')
            ->where('e.bestBefore IS NOT NULL')
            ->andWhere('e.bestBefore <= :cutoffDate')
            ->setParameter('cutoffDate', $cutoff)
            ->orderBy('e.bestBefore', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get stock summary grouped by product.
     *
     * @return array<array{product_id: string, total_quantity: int, earliest_expiry: ?\DateTimeInterface}>
     */
    public function getStockSummary(): array
    {
        // @mago-ignore analysis:mixed-return-statement
        return $this
            ->createQueryBuilder('e')
            ->select(
                'IDENTITY(e.product) as product_id',
                'COUNT(e.id) as total_quantity',
                'MIN(e.bestBefore) as earliest_expiry'
            )
            ->groupBy('e.product')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get stock summary for products with low stock.
     *
     * @return array<array{product_id: string, total_quantity: int, earliest_expiry: ?\DateTimeInterface}>
     */
    public function getStockSummaryLowStock(): array
    {
        // @mago-ignore analysis:mixed-return-statement
        return $this
            ->createQueryBuilder('e')
            ->select(
                'IDENTITY(e.product) as product_id',
                'COUNT(e.id) as total_quantity',
                'MIN(e.bestBefore) as earliest_expiry',
                'p.minStock as min_stock'
            )
            ->join('e.product', 'p')
            ->groupBy('e.product, p.minStock')
            ->having('COUNT(e.id) < p.minStock')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get location breakdown for a product.
     *
     * @return array<array{location_id: string, location_name: string, quantity: int}>
     */
    public function getLocationBreakdown(Uuid $productId): array
    {
        // @mago-ignore analysis:mixed-return-statement
        return $this
            ->createQueryBuilder('e')
            ->select('IDENTITY(e.location) as location_id', 'l.name as location_name', 'COUNT(e.id) as quantity')
            ->join('e.location', 'l')
            ->where('e.product = :productId')
            ->setParameter('productId', $productId)
            ->groupBy('e.location, l.name')
            ->getQuery()
            ->getResult();
    }
}
