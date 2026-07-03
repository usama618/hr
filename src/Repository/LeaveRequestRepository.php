<?php

namespace App\Repository;

use App\Entity\LeaveRequest;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LeaveRequest>
 */
class LeaveRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LeaveRequest::class);
    }

    /**
     * @return list<LeaveRequest>
     */
    public function findRecentForUser(User $employee, int $limit = 20): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.employee = :employee')
            ->setParameter('employee', $employee)
            ->orderBy('l.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<LeaveRequest>
     */
    public function findRecentCompanyRequests(int $limit = 50): array
    {
        return $this->createQueryBuilder('l')
            ->leftJoin('l.employee', 'employee')
            ->addSelect('employee')
            ->orderBy('l.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
