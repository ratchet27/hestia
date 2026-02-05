<?php

declare(strict_types = 1);

namespace App\Repository;

use App\Entity\Task;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Task>
 */
class TaskRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Task::class);
    }

    /**
     * @return Task[]
     */
    public function findActive(): array
    {
        // @mago-ignore analysis:mixed-return-statement
        return $this
            ->createQueryBuilder('t')
            ->where('t.done = false')
            ->orderBy('t.dueDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Task[]
     */
    public function findCompletedRecently(int $days = 3): array
    {
        $since = new \DateTimeImmutable("-{$days} days");
        // @mago-ignore analysis:mixed-return-statement
        return $this
            ->createQueryBuilder('t')
            ->where('t.done = true')
            ->andWhere('t.doneAt >= :since')
            ->setParameter('since', $since)
            ->orderBy('t.doneAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param string $status 'active'|'completed'|'all'
     * @return Task[]
     */
    public function findByStatus(string $status): array
    {
        $qb = $this->createQueryBuilder('t');

        if ($status === 'active') {
            $qb->where('t.done = false');
        } elseif ($status === 'completed') {
            $since = new \DateTimeImmutable('-3 days');
            $qb->where('t.done = true')->andWhere('t.doneAt >= :since')->setParameter('since', $since);
        }

        // @mago-ignore analysis:mixed-return-statement
        return $qb->orderBy('t.createdAt', 'DESC')->getQuery()->getResult();
    }
}
