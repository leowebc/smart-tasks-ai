<?php

namespace App\Repository;

use App\Entity\Task;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Task|null find($id, $lockMode = null, $lockVersion = null)
 * @method Task|null findOneBy(array $criteria, array $orderBy = null)
 * @method Task[]    findAll()
 * @method Task[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
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
    public function findByOwner(User $owner): array
    {
        return $this->findBy(['user' => $owner], ['id' => 'ASC']);
    }

    public function findOneForOwner(int $id, User $owner): ?Task
    {
        return $this->findOneBy(['id' => $id, 'user' => $owner]);
    }

    public function save(Task $task): void
    {
        $manager = $this->getEntityManager();
        $manager->persist($task);
        $manager->flush();
    }

    public function remove(Task $task): void
    {
        $manager = $this->getEntityManager();
        $manager->remove($task);
        $manager->flush();
    }
}
