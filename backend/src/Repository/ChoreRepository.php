<?php

declare(strict_types = 1);

namespace App\Repository;

use App\Entity\Chore;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Chore>
 */
class ChoreRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Chore::class);
    }

    /**
     * @return Chore[]
     */
    public function findAllOrderedByNextDue(): array
    {
        // @mago-ignore analysis:mixed-return-statement
        return $this
            ->createQueryBuilder('c')
            ->orderBy('c.nextDueAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
