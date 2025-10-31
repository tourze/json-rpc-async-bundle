<?php

namespace Tourze\JsonRPCAsyncBundle\Tests\Procedure;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Tourze\JsonRPC\Core\Exception\ApiException;
use Tourze\JsonRPCAsyncBundle\Entity\AsyncResult;
use Tourze\JsonRPCAsyncBundle\Procedure\GetAsyncRequestResult;
use Tourze\JsonRPCAsyncBundle\Repository\AsyncResultRepository;
use Tourze\JsonRPCAsyncBundle\Tests\Fixtures\TestCacheAdapter;

/**
 * @internal
 */
// @phpstan-ignore-next-line
#[CoversClass(GetAsyncRequestResult::class)]
#[RunTestsInSeparateProcesses]
final class GetAsyncRequestResultTest extends TestCase
{
    private AsyncResultRepository&MockObject $resultRepository;

    private LoggerInterface&MockObject $logger;

    private TestCacheAdapter $cache;

    private GetAsyncRequestResult $procedure;

    protected function setUp(): void
    {
        $this->resultRepository = $this->createMock(AsyncResultRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->cache = new TestCacheAdapter();

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

    public function testExecuteCacheHitReturnsResultFromCache(): void
    {
        $taskId = 'test-task-123';
        $this->procedure->taskId = $taskId;

        $cacheResult = [
            'jsonrpc' => '2.0',
            'result' => ['data' => 'success', 'code' => 200],
            'id' => 'test',
        ];

        $this->cache->setTestData(AsyncResult::CACHE_PREFIX . $taskId, $cacheResult);

        // 缓存命中，不应该查询数据库
        $this->resultRepository->expects($this->never())->method('findOneBy');

        $result = $this->procedure->execute();

        $this->assertEquals(['data' => 'success', 'code' => 200], $result);
    }

    public function testExecuteCacheMissQueriesDatabase(): void
    {
        $taskId = 'test-task-456';
        $this->procedure->taskId = $taskId;

        // 缓存中没有数据

        $dbResult = [
            'jsonrpc' => '2.0',
            'result' => ['message' => 'database result'],
            'id' => 'test',
        ];

        // Mock 数据库返回
        $asyncResult = new AsyncResult();
        $asyncResult->setTaskId($taskId);
        $asyncResult->setResult($dbResult);

        $this->resultRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['taskId' => $taskId])
            ->willReturn($asyncResult)
        ;

        $result = $this->procedure->execute();

        $this->assertEquals(['message' => 'database result'], $result);
    }

    public function testExecuteTaskNotFoundThrowsException(): void
    {
        $taskId = 'non-existent-task-' . uniqid();
        $this->procedure->taskId = $taskId;

        // 缓存中没有数据，数据库也没有
        $this->resultRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['taskId' => $taskId])
            ->willReturn(null)
        ;

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('未执行完成');
        $this->expectExceptionCode(-789);

        $this->procedure->execute();
    }

    public function testExecuteResultWithErrorThrowsApiException(): void
    {
        $taskId = 'error-task';
        $this->procedure->taskId = $taskId;

        $errorResult = [
            'jsonrpc' => '2.0',
            'error' => [
                'code' => -1001,
                'message' => 'Custom error message',
                'data' => ['details' => 'Error details'],
            ],
            'id' => 'test',
        ];

        $this->cache->setTestData(AsyncResult::CACHE_PREFIX . $taskId, $errorResult);

        // 缓存命中，不需要查询数据库

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Custom error message');
        $this->expectExceptionCode(-1001);

        $this->procedure->execute();
    }

    public function testExecuteResultWithErrorFromDatabaseThrowsApiException(): void
    {
        $taskId = 'db-error-task-' . uniqid();
        $this->procedure->taskId = $taskId;

        // 缓存中没有数据

        $errorResult = [
            'jsonrpc' => '2.0',
            'error' => [
                'code' => -999,
                'message' => 'Database error',
            ],
            'id' => 'test',
        ];

        // Mock 数据库返回错误结果
        $asyncResult = new AsyncResult();
        $asyncResult->setTaskId($taskId);
        $asyncResult->setResult($errorResult);

        $this->resultRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['taskId' => $taskId])
            ->willReturn($asyncResult)
        ;

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Database error');
        $this->expectExceptionCode(-999);

        $this->procedure->execute();
    }

    public function testExecuteEmptyResultReturnsEmptyArray(): void
    {
        $taskId = 'empty-task';
        $this->procedure->taskId = $taskId;

        $emptyResult = [
            'jsonrpc' => '2.0',
            'result' => null,
            'id' => 'test',
        ];

        $this->cache->setTestData(AsyncResult::CACHE_PREFIX . $taskId, $emptyResult);

        $result = $this->procedure->execute();

        $this->assertEquals([], $result);
    }

    public function testExecuteArrayResultReturnsCorrectly(): void
    {
        $taskId = 'array-task';
        $this->procedure->taskId = $taskId;

        $arrayResult = [
            'jsonrpc' => '2.0',
            'result' => [
                'items' => [1, 2, 3],
                'total' => 3,
                'status' => 'success',
            ],
            'id' => 'test',
        ];

        $this->cache->setTestData(AsyncResult::CACHE_PREFIX . $taskId, $arrayResult);

        $result = $this->procedure->execute();

        $this->assertEquals([
            'items' => [1, 2, 3],
            'total' => 3,
            'status' => 'success',
        ], $result);
    }

    public function testExecuteBooleanResultConvertedToArray(): void
    {
        $taskId = 'boolean-task';
        $this->procedure->taskId = $taskId;

        $booleanResult = [
            'jsonrpc' => '2.0',
            'result' => true,
            'id' => 'test',
        ];

        $this->cache->setTestData(AsyncResult::CACHE_PREFIX . $taskId, $booleanResult);

        $result = $this->procedure->execute();

        $this->assertEquals([true], $result);
    }

    public function testExecuteStringResultConvertedToArray(): void
    {
        $taskId = 'string-task';
        $this->procedure->taskId = $taskId;

        $stringResult = [
            'jsonrpc' => '2.0',
            'result' => 'simple string result',
            'id' => 'test',
        ];

        $this->cache->setTestData(AsyncResult::CACHE_PREFIX . $taskId, $stringResult);

        $result = $this->procedure->execute();

        $this->assertEquals(['simple string result'], $result);
    }

    public function testTaskIdPropertyCanBeSet(): void
    {
        $taskId = 'property-test';
        $this->procedure->taskId = $taskId;

        $this->assertEquals($taskId, $this->procedure->taskId);
    }
}
