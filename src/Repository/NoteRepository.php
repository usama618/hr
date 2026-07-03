<?php

namespace App\Repository;

use App\Entity\Note;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Note>
 */
class NoteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Note::class);
    }

    /**
     * @return list<Note>
     */
    public function findForOwner(User $owner, ?string $query = null, ?string $notebook = null, bool $pinnedOnly = false): array
    {
        $builder = $this->createQueryBuilder('n')
            ->andWhere('n.owner = :owner')
            ->setParameter('owner', $owner);

        if ($query !== null && trim($query) !== '') {
            $builder
                ->andWhere('LOWER(n.title) LIKE :query OR LOWER(n.body) LIKE :query')
                ->setParameter('query', '%'.strtolower(trim($query)).'%');
        }

        if ($notebook !== null && trim($notebook) !== '') {
            $builder
                ->andWhere('n.notebook = :notebook')
                ->setParameter('notebook', trim($notebook));
        }

        if ($pinnedOnly) {
            $builder->andWhere('n.isPinned = true');
        }

        return $builder
            ->orderBy('n.isPinned', 'DESC')
            ->addOrderBy('n.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<string>
     */
    public function findNotebookNamesForOwner(User $owner): array
    {
        $rows = $this->createQueryBuilder('n')
            ->select('DISTINCT n.notebook AS notebook')
            ->andWhere('n.owner = :owner')
            ->andWhere('n.notebook IS NOT NULL')
            ->setParameter('owner', $owner)
            ->orderBy('n.notebook', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $notebooks = [];
        foreach ($rows as $row) {
            $notebook = trim((string) ($row['notebook'] ?? ''));
            if ($notebook !== '') {
                $notebooks[] = $notebook;
            }
        }

        return $notebooks;
    }
}
