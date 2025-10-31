<?php

namespace Tourze\JsonRPCAsyncBundle\Tests\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\JsonRPCAsyncBundle\Entity\AsyncResult;
use Tourze\JsonRPCAsyncBundle\Repository\AsyncResultRepository;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;

/**
 * @internal
 */
#[CoversClass(AsyncResultRepository::class)]
#[RunTestsInSeparateProcesses]
final class AsyncResultRepositoryTest extends AbstractRepositoryTestCase
{
    private AsyncResultRepository $repository;

    protected function onSetUp(): void
    {
        $this->repository = self::getService(AsyncResultRepository::class);
    }

    public function testSave(): void
    {
        $entity = new AsyncResult();
        $entity->setTaskId('test-task-' . uniqid());
        $entity->setResult(['status' => 'success']);

        $this->repository->save($entity);

        $this->assertNotNull($entity->getId());
        $this->assertEquals('test-task-' . substr($entity->getTaskId(), -13), $entity->getTaskId());
    }

    public function testRemove(): void
    {
        $entity = new AsyncResult();
        $entity->setTaskId('test-task-remove-' . uniqid());
        $entity->setResult(['status' => 'success']);
        $this->repository->save($entity);

        $savedId = $entity->getId();
        $this->repository->remove($entity);

        $removed = $this->repository->find($savedId);
        $this->assertNull($removed);
    }

    public function testFindOneByWithSortingShouldRespectOrderBy(): void
    {
        $entity1 = new AsyncResult();
        $entity1->setTaskId('sort-test-z-' . uniqid());
        $entity1->setResult(['priority' => 'low']);
        $this->repository->save($entity1, false);

        $entity2 = new AsyncResult();
        $entity2->setTaskId('sort-test-a-' . uniqid());
        $entity2->setResult(['priority' => 'high']);
        $this->repository->save($entity2);

        $result = $this->repository->findOneBy([], ['taskId' => 'ASC']);
        $this->assertInstanceOf(AsyncResult::class, $result);
    }

    public function testFindByWithNullResultShouldReturnMatches(): void
    {
        $entity = new AsyncResult();
        $entity->setTaskId('null-result-test-' . uniqid());
        $entity->setResult(null);
        $this->repository->save($entity);

        $results = $this->repository->findBy(['result' => null]);
        $this->assertIsArray($results);
        $this->assertGreaterThanOrEqual(1, count($results));
    }

    public function testCountWithNullResultShouldReturnCount(): void
    {
        $entity = new AsyncResult();
        $entity->setTaskId('null-count-test-' . uniqid());
        $entity->setResult(null);
        $this->repository->save($entity);

        $count = $this->repository->count(['result' => null]);
        $this->assertIsInt($count);
        $this->assertGreaterThanOrEqual(1, $count);
    }

    public function testFindOneByWithNullResultShouldReturnEntity(): void
    {
        $taskId = 'null-findone-test-' . uniqid();
        $entity = new AsyncResult();
        $entity->setTaskId($taskId);
        $entity->setResult(null);
        $this->repository->save($entity);

        $result = $this->repository->findOneBy(['taskId' => $taskId, 'result' => null]);
        $this->assertInstanceOf(AsyncResult::class, $result);
        $this->assertNull($result->getResult());
    }

    // ========== findOneBy 排序逻辑测试 ==========

    public function testFindOneByWithComplexOrderingShouldRespectMultipleFields(): void
    {
        $prefix = 'complex-order-' . uniqid();

        $entity1 = new AsyncResult();
        $entity1->setTaskId($prefix . '-b');
        $entity1->setResult(['priority' => 1]);
        $this->repository->save($entity1, false);

        $entity2 = new AsyncResult();
        $entity2->setTaskId($prefix . '-a');
        $entity2->setResult(['priority' => 2]);
        $this->repository->save($entity2);

        $result = $this->repository->findOneBy([], ['taskId' => 'ASC', 'id' => 'DESC']);
        $this->assertInstanceOf(AsyncResult::class, $result);
    }

    protected function createNewEntity(): object
    {
        $entity = new AsyncResult();
        $entity->setTaskId('test-' . uniqid());
        $entity->setResult(['status' => 'test']);

        return $entity;
    }

    /**
     * @return ServiceEntityRepository<AsyncResult>
     */
    protected function getRepository(): ServiceEntityRepository
    {
        return $this->repository;
    }
}
