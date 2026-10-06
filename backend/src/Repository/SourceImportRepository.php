<?php

namespace App\Repository;

use App\Entity\SourceImport;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class SourceImportRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SourceImport::class);
    }

    /** @return SourceImport[] */
    public function findByOwner(User $owner): array
    {
        return $this->findBy(['user' => $owner], ['id' => 'DESC']);
    }

    public function findOneForOwner(int $id, User $owner): ?SourceImport
    {
        return $this->findOneBy(['id' => $id, 'user' => $owner]);
    }

    public function save(SourceImport $import): void
    {
        $manager = $this->getEntityManager();
        $manager->persist($import);
        $manager->flush();
    }

    public function remove(SourceImport $import): void
    {
        $manager = $this->getEntityManager();
        $manager->remove($import);
        $manager->flush();
    }
}
