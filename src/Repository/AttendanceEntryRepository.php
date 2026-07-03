<?php

namespace App\Repository;

use App\Entity\AttendanceEntry;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AttendanceEntry>
 */
class AttendanceEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AttendanceEntry::class);
    }

    public function findOpenForUser(User $employee): ?AttendanceEntry
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.employee = :employee')
            ->andWhere('a.checkOutAt IS NULL')
            ->setParameter('employee', $employee)
            ->orderBy('a.checkInAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<AttendanceEntry>
     */
    public function findRecentForUser(User $employee, int $limit = 10): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.employee = :employee')
            ->setParameter('employee', $employee)
            ->orderBy('a.checkInAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<AttendanceEntry>
     */
    public function findForUserBetween(User $employee, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.breaks', 'breaks')
            ->addSelect('breaks')
            ->andWhere('a.employee = :employee')
            ->andWhere('a.checkInAt >= :from')
            ->andWhere('a.checkInAt < :to')
            ->setParameter('employee', $employee)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('a.checkInAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<AttendanceEntry>
     */
    public function findRecentCompanyEntries(int $limit = 50): array
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.employee', 'employee')
            ->addSelect('employee')
            ->orderBy('a.checkInAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<AttendanceEntry>
     */
    public function findCompanyEntriesBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.employee', 'employee')
            ->leftJoin('a.breaks', 'breaks')
            ->addSelect('employee', 'breaks')
            ->andWhere('a.checkInAt >= :from')
            ->andWhere('a.checkInAt < :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('employee.fullName', 'ASC')
            ->addOrderBy('a.checkInAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
