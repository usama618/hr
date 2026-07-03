<?php

namespace App\Repository;

use App\Entity\Task;
use App\Entity\TaskTimeEntry;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TaskTimeEntry>
 */
class TaskTimeEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TaskTimeEntry::class);
    }

    public function findOpenForUser(User $employee): ?TaskTimeEntry
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.employee = :employee')
            ->andWhere('t.endedAt IS NULL')
            ->setParameter('employee', $employee)
            ->orderBy('t.startedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOpenForUserAndTask(User $employee, Task $task): ?TaskTimeEntry
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.employee = :employee')
            ->andWhere('t.task = :task')
            ->andWhere('t.endedAt IS NULL')
            ->setParameter('employee', $employee)
            ->setParameter('task', $task)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<TaskTimeEntry>
     */
    public function findRecentCompanyEntries(int $limit = 80): array
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.employee', 'employee')
            ->leftJoin('t.task', 'task')
            ->leftJoin('task.project', 'project')
            ->addSelect('employee', 'task', 'project')
            ->orderBy('t.startedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
