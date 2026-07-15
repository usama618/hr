<?php

namespace App\Repository;

use App\Entity\Project;
use App\Entity\Task;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Task> */
final class TaskRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Task::class);
    }

    /** @return list<Task> */
    public function findWorkspaceTasks(Project $project): array
    {
        return $this->createQueryBuilder('t')
            ->addSelect('assignees')
            ->leftJoin('t.assignees', 'assignees')
            ->andWhere('t.project = :project')
            ->setParameter('project', $project)
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<Task> */
    public function findAssignedTo(User $employee): array
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.project', 'project')
            ->addSelect('project')
            ->leftJoin('t.parent', 'parent')
            ->addSelect('parent')
            ->leftJoin('t.assignees', 'assignees')
            ->addSelect('assignees')
            ->andWhere(':employee MEMBER OF t.assignees')
            ->setParameter('employee', $employee)
            ->orderBy('project.name', 'ASC')
            ->addOrderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
