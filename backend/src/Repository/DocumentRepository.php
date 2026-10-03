<?php

namespace App\Repository;

use App\Entity\Document;
use App\Entity\SourceImport;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class DocumentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Document::class);
    }

    /** @return Document[] */
    public function findByOwner(User $owner): array
    {
        return $this->findBy(['user' => $owner], ['id' => 'DESC']);
    }

    /** @return Document[] */
    public function findFilesByOwner(User $owner): array
    {
        return $this->createQueryBuilder('document')
            ->andWhere('document.user = :owner')
            ->andWhere('document.sourceUrl IS NULL')
            ->orderBy('document.id', 'DESC')
            ->setParameter('owner', $owner)
            ->getQuery()
            ->getResult();
    }

    /** @return Document[] */
    public function findByImport(User $owner, SourceImport $import): array
    {
        return $this->createQueryBuilder('document')
            ->andWhere('document.user = :owner')
            ->andWhere('document.import = :import')
            ->orderBy('document.id', 'ASC')
            ->setParameter('owner', $owner)
            ->setParameter('import', $import)
            ->getQuery()
            ->getResult();
    }

    public function countByImport(User $owner, SourceImport $import): int
    {
        return (int) $this->createQueryBuilder('document')
            ->select('COUNT(document.id)')
            ->andWhere('document.user = :owner')
            ->andWhere('document.import = :import')
            ->setParameter('owner', $owner)
            ->setParameter('import', $import)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return Document[] */
    public function findUngroupedSources(User $owner): array
    {
        return $this->createQueryBuilder('document')
            ->andWhere('document.user = :owner')
            ->andWhere('document.sourceUrl IS NOT NULL')
            ->andWhere('document.import IS NULL')
            ->orderBy('document.id', 'DESC')
            ->setParameter('owner', $owner)
            ->getQuery()
            ->getResult();
    }

    /** @return Document[] */
    public function findSourcesByOwner(User $owner): array
    {
        return $this->createQueryBuilder('document')
            ->andWhere('document.user = :owner')
            ->andWhere('document.sourceUrl IS NOT NULL')
            ->orderBy('document.id', 'DESC')
            ->setParameter('owner', $owner)
            ->getQuery()
            ->getResult();
    }

    public function findSourceByUrl(User $owner, string $url): ?Document
    {
        return $this->createQueryBuilder('document')
            ->andWhere('document.user = :owner')
            ->andWhere('document.sourceUrl = :url')
            ->setParameter('owner', $owner)
            ->setParameter('url', $url)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOneForOwner(int $id, User $owner): ?Document
    {
        return $this->findOneBy(['id' => $id, 'user' => $owner]);
    }

    public function save(Document $document): void
    {
        $manager = $this->getEntityManager();
        $manager->persist($document);
        $manager->flush();
    }

    public function remove(Document $document): void
    {
        $manager = $this->getEntityManager();
        $manager->remove($document);
        $manager->flush();
    }
}
