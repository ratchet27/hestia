<?php

declare(strict_types = 1);

namespace App\Repository;

use App\Entity\StockEntry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Types\Types;
use Doctrine\DBAL\LockMode;
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
     * Rows are locked FOR UPDATE, so two concurrent consumers cannot both select
     * the same last unit. Must be called inside a transaction.
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
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->getResult();
    }

    /**
     * Find entries for FIFO consumption across ALL locations (earliest best_before first, NULL last).
     *
     * Rows are locked FOR UPDATE; must be called inside a transaction.
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
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
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
            ->createQueryBuilderWithRelations()
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
            ->createQueryBuilderWithRelations()
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
            ->createQueryBuilderWithRelations()
            ->where('e.bestBefore IS NOT NULL')
            ->andWhere('e.bestBefore <= :cutoffDate')
            ->setParameter('cutoffDate', $cutoff, Types::DATE_IMMUTABLE)
            ->orderBy('e.bestBefore', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * All entries with product and location loaded, in the same order as the
     * filtered variants.
     *
     * @return StockEntry[]
     */
    public function findAllWithRelations(): array
    {
        // @mago-ignore analysis:mixed-return-statement
        return $this
            ->createQueryBuilderWithRelations()
            ->orderBy('e.bestBefore', 'ASC')
            ->addOrderBy('e.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Entry count per product for a set of products, in one query.
     *
     * @param Uuid[] $productIds
     *
     * @return array<string, int> keyed by RFC 4122 product id; every requested id present (0 when no stock)
     */
    public function countByProducts(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        $keys = array_map(static fn(Uuid $id): string => $id->toRfc4122(), $productIds);
        $counts = array_fill_keys($keys, 0);

        /** @var list<array{product_id: string, quantity: string|int}> $rows */
        $rows = $this
            ->createQueryBuilder('e')
            ->select('IDENTITY(e.product) as product_id', 'COUNT(e.id) as quantity')
            ->where('e.product IN (:ids)')
            ->setParameter('ids', $keys, ArrayParameterType::STRING)
            ->groupBy('e.product')
            ->getQuery()
            ->getResult();

        foreach ($rows as $row) {
            $counts[$row['product_id']] = (int) $row['quantity'];
        }

        return $counts;
    }

    /**
     * Entry count per location across all products, in one query.
     *
     * @return array<string, int> keyed by RFC 4122 location id
     */
    public function countByLocationGrouped(): array
    {
        /** @var list<array{location_id: string, quantity: string|int}> $rows */
        $rows = $this
            ->createQueryBuilder('e')
            ->select('IDENTITY(e.location) as location_id', 'COUNT(e.id) as quantity')
            ->groupBy('e.location')
            ->getQuery()
            ->getResult();

        return array_column(
            array_map(static fn(array $row): array => [
                'id' => $row['location_id'],
                'n' => (int) $row['quantity']
            ], $rows),
            'n',
            'id'
        );
    }

    /**
     * Location breakdown for every product in one query (the per-product
     * variant is getLocationBreakdown).
     *
     * @return array<array{product_id: string, location_id: string, location_name: string, quantity: int}>
     */
    public function getLocationBreakdownForAll(): array
    {
        // @mago-ignore analysis:mixed-return-statement
        return $this
            ->createQueryBuilder('e')
            ->select(
                'IDENTITY(e.product) as product_id',
                'IDENTITY(e.location) as location_id',
                'l.name as location_name',
                'COUNT(e.id) as quantity'
            )
            ->join('e.location', 'l')
            ->groupBy('e.product, e.location, l.name')
            ->orderBy('l.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    private function createQueryBuilderWithRelations(): \Doctrine\ORM\QueryBuilder
    {
        // Response DTOs read product name/unit and location name for every row;
        // fetch-join them so a list is one query instead of 1 + 2N lazy loads.
        return $this
            ->createQueryBuilder('e')
            ->join('e.product', 'p')
            ->addSelect('p')
            ->join('e.location', 'l')
            ->addSelect('l');
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
