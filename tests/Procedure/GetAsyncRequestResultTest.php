<?php

namespace Tourze\JsonRPCAsyncBundle\Tests\Procedure;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Tourze\JsonRPCAsyncBundle\Procedure\GetAsyncRequestResult;
use Tourze\JsonRPCAsyncBundle\Repository\AsyncResultRepository;

class GetAsyncRequestResultTest extends TestCase
{
    private MockObject|AsyncResultRepository $resultRepository;
    private MockObject|LoggerInterface $logger;
    private MockObject|CacheInterface $cache;
    private GetAsyncRequestResult $procedure;

    protected function setUp(): void
    {
        $this->resultRepository = $this->createMock(AsyncResultRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->cache = $this->createMock(CacheInterface::class);

        $this->procedure = new GetAsyncRequestResult(
            $this->resultRepository,
            $this->logger,
            $this->cache
        );
    }

    public function testProcedureCanBeConstructed(): void
    {
        $this->assertInstanceOf(GetAsyncRequestResult::class, $this->procedure);
    }
}
