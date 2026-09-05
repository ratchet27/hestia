<?php

declare(strict_types = 1);

namespace App\Repository;

use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    /**
     * @param array{name?: string, category_id?: string, active?: bool} $filters
     * @return Product[]
     */
    public function findByFilters(array $filters): array
    {
        $qb = $this
            ->createQueryBuilder('p')
            ->leftJoin('p.barcodes', 'b')
            ->leftJoin('p.category', 'c')
            ->leftJoin('p.defaultLocation', 'l')
            ->addSelect('b', 'c', 'l')
            ->orderBy('p.name', 'ASC');

        if (isset($filters['name'])) {
            $qb->andWhere('LOWER(p.name) LIKE LOWER(:name)')->setParameter('name', '%' . $filters['name'] . '%');
        }

        if (isset($filters['category_id'])) {
            $qb->andWhere('c.id = :categoryId')->setParameter('categoryId', Uuid::fromString($filters['category_id']));
        }

        if (isset($filters['active'])) {
            $qb->andWhere('p.active = :active')->setParameter('active', $filters['active']);
        }

        // @mago-ignore analysis:mixed-return-statement
        return $qb->getQuery()->getResult();
    }

    /**
     * @param Uuid[] $ids
     *
     * @return array<string, Product> keyed by RFC 4122 id
     */
    public function findByIdsIndexed(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        /** @var Product[] $products */
        $products = $this->findBy(['id' => $ids]);

        $indexed = [];
        foreach ($products as $product) {
            $indexed[$product->getId()->toRfc4122()] = $product;
        }

        return $indexed;
    }

    /**
     * Product count per category, in one query.
     *
     * @return array<string, int> keyed by RFC 4122 category id
     */
    public function countByCategoryGrouped(): array
    {
        return $this->countGroupedBy('p.category');
    }

    /**
     * Product count per default location, in one query.
     *
     * @return array<string, int> keyed by RFC 4122 location id
     */
    public function countByDefaultLocationGrouped(): array
    {
        return $this->countGroupedBy('p.defaultLocation');
    }

    /** @return array<string, int> */
    private function countGroupedBy(string $association): array
    {
        /** @var list<array{group_id: string, quantity: string|int}> $rows */
        $rows = $this
            ->createQueryBuilder('p')
            ->select(sprintf('IDENTITY(%s) as group_id', $association), 'COUNT(p.id) as quantity')
            ->groupBy($association)
            ->getQuery()
            ->getResult();

        return array_column(
            array_map(static fn(array $row): array => ['id' => $row['group_id'], 'n' => (int) $row['quantity']], $rows),
            'n',
            'id'
        );
    }

    public function exists(Uuid $id): bool
    {
        return null !== $this
            ->createQueryBuilder('p')
            ->select('1')
            ->where('p.id = :id')
            ->setParameter('id', $id)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOneWithBarcodes(Uuid $id): ?Product
    {
        // @mago-ignore analysis:mixed-return-statement
        return $this
            ->createQueryBuilder('p')
            ->leftJoin('p.barcodes', 'b')
            ->leftJoin('p.category', 'c')
            ->leftJoin('p.defaultLocation', 'l')
            ->addSelect('b', 'c', 'l')
            ->where('p.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
