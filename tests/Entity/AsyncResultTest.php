<?php

namespace Tourze\JsonRPCAsyncBundle\Tests\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\JsonRPCAsyncBundle\Entity\AsyncResult;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;

/**
 * @internal
 */
#[CoversClass(AsyncResult::class)]
final class AsyncResultTest extends AbstractEntityTestCase
{
    protected function createEntity(): AsyncResult
    {
        return new AsyncResult();
    }

    /**
     * 提供 AsyncResult 实体属性及其样本值的 Data Provider.
     */
    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function propertiesProvider(): iterable
    {
        yield 'taskId' => ['taskId', 'test-task-id-123'];
        yield 'result' => ['result', ['key' => 'value', 'nested' => ['data' => true]]];
        yield 'resultNull' => ['result', null];
    }

    public function testConstructDefaultValues(): void
    {
        $entity = new AsyncResult();
        $this->assertNull($entity->getCreateTime());
        $this->assertNull($entity->getResult());
    }

    public function testSetAndGetCreateTimeValidDateTime(): void
    {
        $entity = new AsyncResult();
        $now = new \DateTimeImmutable();

        $entity->setCreateTime($now);
        $createTime = $entity->getCreateTime();

        $this->assertInstanceOf(\DateTimeImmutable::class, $createTime);
        $this->assertEquals($now->format('Y-m-d H:i:s'), $createTime->format('Y-m-d H:i:s'));
    }
}
