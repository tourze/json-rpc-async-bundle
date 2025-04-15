<?php

namespace Tourze\JsonRPCAsyncBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Tourze\JsonRPCAsyncBundle\Entity\AsyncResult;

/**
 * @extends ServiceEntityRepository<AsyncResult>
 *
 * @method AsyncResult|null find($id, $lockMode = null, $lockVersion = null)
 * @method AsyncResult|null findOneBy(array $criteria, array $orderBy = null)
 * @method AsyncResult[]    findAll()
 * @method AsyncResult[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AsyncResultRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AsyncResult::class);
    }
}
