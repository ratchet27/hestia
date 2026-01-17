<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Barcode;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Barcode>
 */
class BarcodeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Barcode::class);
    }

    public function findByCode(string $code): ?Barcode
    {
        return $this->createQueryBuilder('b')
            ->leftJoin('b.product', 'p')
            ->leftJoin('p.category', 'c')
            ->leftJoin('p.defaultLocation', 'l')
            ->addSelect('p', 'c', 'l')
            ->where('b.barcode = :code')
            ->setParameter('code', $code)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return Barcode[] */
    public function findByProduct(Uuid $productId): array
    {
        return $this->createQueryBuilder('b')
            ->leftJoin('b.product', 'p')
            ->where('p.id = :productId')
            ->setParameter('productId', $productId)
            ->orderBy('b.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByProductAndCode(Uuid $productId, string $code): ?Barcode
    {
        return $this->createQueryBuilder('b')
            ->leftJoin('b.product', 'p')
            ->where('p.id = :productId')
            ->andWhere('b.barcode = :code')
            ->setParameter('productId', $productId)
            ->setParameter('code', $code)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
