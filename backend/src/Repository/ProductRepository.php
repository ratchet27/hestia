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
