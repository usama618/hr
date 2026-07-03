<?php

namespace App\Repository;

use App\Entity\EmployeeDocument;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EmployeeDocument>
 */
class EmployeeDocumentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EmployeeDocument::class);
    }

    /**
     * @return list<EmployeeDocument>
     */
    public function findVisibleForUser(User $user, bool $isAdmin, ?string $query = null, ?string $category = null): array
    {
        $builder = $this->createQueryBuilder('d')
            ->leftJoin('d.owner', 'owner')
            ->addSelect('owner')
            ->leftJoin('d.uploadedBy', 'uploadedBy')
            ->addSelect('uploadedBy');

        if (!$isAdmin) {
            $builder
                ->andWhere('d.owner IS NULL OR d.owner = :user')
                ->setParameter('user', $user);
        }

        if ($query !== null && trim($query) !== '') {
            $builder
                ->andWhere('LOWER(d.title) LIKE :query OR LOWER(d.originalFilename) LIKE :query OR LOWER(d.description) LIKE :query')
                ->setParameter('query', '%'.strtolower(trim($query)).'%');
        }

        if ($category !== null && trim($category) !== '') {
            $builder
                ->andWhere('d.category = :category')
                ->setParameter('category', trim($category));
        }

        return $builder
            ->orderBy('d.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
