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

    /** @return list<TaskTimeEntry> */
    public function findForUserBetween(User $employee, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.task', 'task')
            ->addSelect('task')
            ->leftJoin('task.project', 'project')
            ->addSelect('project')
            ->andWhere('t.employee = :employee')
            ->andWhere('t.startedAt >= :from')
            ->andWhere('t.startedAt < :to')
            ->setParameter('employee', $employee)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('t.startedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return array<int, int> */
    public function sumSecondsByTaskForUser(User $employee): array
    {
        $entries = $this->createQueryBuilder('t')
            ->leftJoin('t.task', 'task')
            ->addSelect('task')
            ->andWhere('t.employee = :employee')
            ->setParameter('employee', $employee)
            ->getQuery()
            ->getResult();
        $totals = [];

        foreach ($entries as $entry) {
            if (!$entry instanceof TaskTimeEntry || !$entry->getTask()?->getId()) {
                continue;
            }
            $taskId = $entry->getTask()->getId();
            $totals[$taskId] = ($totals[$taskId] ?? 0) + $entry->getSeconds();
        }

        return $totals;
    }

    /**
     * @return list<TaskTimeEntry>
     */
    public function findRecentCompanyEntries(int $limit = 80): array
    {
        return $this->findCompanyEntries($limit);
    }

    /**
     * @return list<TaskTimeEntry>
     */
    public function findCompanyEntries(?int $limit = null): array
    {
        $builder = $this->createQueryBuilder('t')
            ->leftJoin('t.employee', 'employee')
            ->leftJoin('t.task', 'task')
            ->leftJoin('task.project', 'project')
            ->addSelect('employee', 'task', 'project')
            ->orderBy('t.startedAt', 'DESC');

        if ($limit !== null) {
            $builder->setMaxResults($limit);
        }

        return $builder
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<TaskTimeEntry>
     */
    public function findCompanyEntriesBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.employee', 'employee')
            ->leftJoin('t.task', 'task')
            ->leftJoin('task.project', 'project')
            ->addSelect('employee', 'task', 'project')
            ->andWhere('t.startedAt >= :from')
            ->andWhere('t.startedAt < :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('t.startedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
