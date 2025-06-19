<?php

namespace Tourze\JsonRPCAsyncBundle\Tests\Entity;

use PHPUnit\Framework\TestCase;
use Tourze\JsonRPCAsyncBundle\Entity\AsyncResult;

class AsyncResultTest extends TestCase
{
    public function testConstruct_defaultValues(): void
    {
        $entity = new AsyncResult();
        $this->assertNull($entity->getId());
        $this->assertNull($entity->getCreateTime());
        $this->assertNull($entity->getResult());
    }

    public function testSetAndGetTaskId_validValue(): void
    {
        $entity = new AsyncResult();
        $taskId = 'test-task-id-123';

        $entity->setTaskId($taskId);
        $this->assertSame($taskId, $entity->getTaskId());
    }

    public function testSetAndGetResult_validArray(): void
    {
        $entity = new AsyncResult();
        $result = ['key' => 'value', 'nested' => ['data' => true]];

        $entity->setResult($result);
        $this->assertSame($result, $entity->getResult());
    }

    public function testSetAndGetResult_nullValue(): void
    {
        $entity = new AsyncResult();
        $entity->setResult(null);
        $this->assertNull($entity->getResult());
    }

    public function testSetAndGetCreateTime_validDateTime(): void
    {
        $entity = new AsyncResult();
        $now = new \DateTimeImmutable();

        $entity->setCreateTime($now);
        $createTime = $entity->getCreateTime();
        
        $this->assertInstanceOf(\DateTimeImmutable::class, $createTime);
        $this->assertEquals($now->format('Y-m-d H:i:s'), $createTime->format('Y-m-d H:i:s'));
    }
}
