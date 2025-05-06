<?php

namespace Tourze\JsonRPCAsyncBundle\Tests\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tourze\JsonRPCAsyncBundle\Repository\AsyncResultRepository;

class AsyncResultRepositoryTest extends TestCase
{
    private MockObject|ManagerRegistry $registry;
    private AsyncResultRepository $repository;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(ManagerRegistry::class);

        // 不验证getManagerForClass的调用
        $this->registry->method('getManagerForClass')
            ->willReturn($this->createMock(EntityManagerInterface::class));

        $this->repository = new AsyncResultRepository($this->registry);
    }

    public function testRepositoryCanBeConstructed(): void
    {
        $this->assertInstanceOf(AsyncResultRepository::class, $this->repository);
    }
}
