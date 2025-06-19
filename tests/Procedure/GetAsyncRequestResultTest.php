<?php

namespace Tourze\JsonRPCAsyncBundle\Tests\Procedure;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Tourze\JsonRPC\Core\Exception\ApiException;
use Tourze\JsonRPCAsyncBundle\Entity\AsyncResult;
use Tourze\JsonRPCAsyncBundle\Procedure\GetAsyncRequestResult;
use Tourze\JsonRPCAsyncBundle\Repository\AsyncResultRepository;

/**
 * 创建一个自定义的缓存实现来支持 getItem 方法
 */
class TestCacheAdapter implements CacheInterface
{
    private array $items = [];
    
    public function getItem(string $key): CacheItemInterface
    {
        $cacheItem = new class($key, $this->items[$key] ?? null, isset($this->items[$key])) implements CacheItemInterface {
            public function __construct(
                private string $key,
                private mixed $value,
                private bool $isHit
            ) {}
            
            public function getKey(): string { return $this->key; }
            public function get(): mixed { return $this->value; }
            public function isHit(): bool { return $this->isHit; }
            public function set(mixed $value): static { $this->value = $value; return $this; }
            public function expiresAt(?\DateTimeInterface $expiration): static { return $this; }
            public function expiresAfter(int|\DateInterval|null $time): static { return $this; }
        };
        
        return $cacheItem;
    }
    
    public function setTestData(string $key, mixed $value): void
    {
        $this->items[$key] = $value;
    }
    
    public function get(string $key, callable $callback, ?float $beta = null, ?array &$metadata = null): mixed
    {
        if (array_key_exists($key, $this->items)) {
            return $this->items[$key];
        }
        
        // 创建一个模拟的 ItemInterface
        $item = new class($key) implements \Symfony\Contracts\Cache\ItemInterface {
            public function __construct(private string $key) {}
            public function getKey(): string { return $this->key; }
            public function get(): mixed { return null; }
            public function isHit(): bool { return false; }
            public function set(mixed $value): static { return $this; }
            public function expiresAt(?\DateTimeInterface $expiration): static { return $this; }
            public function expiresAfter(\DateInterval|int|null $time): static { return $this; }
            public function tag(string|iterable $tags): static { return $this; }
            public function getMetadata(): array { return []; }
        };
        
        $value = $callback($item, true);
        $this->items[$key] = $value;
        return $value;
    }
    
    public function delete(string $key): bool
    {
        unset($this->items[$key]);
        return true;
    }
}

class GetAsyncRequestResultTest extends TestCase
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

    public function testExecute_cacheHit_returnsResultFromCache(): void
    {
        $taskId = 'test-task-123';
        $this->procedure->taskId = $taskId;

        $cacheResult = [
            'jsonrpc' => '2.0',
            'result' => ['data' => 'success', 'code' => 200],
            'id' => 'test'
        ];

        $this->cache->setTestData(AsyncResult::CACHE_PREFIX . $taskId, $cacheResult);

        // 不应该查询数据库
        $this->resultRepository->expects($this->never())
            ->method('findOneBy');

        $result = $this->procedure->execute();

        $this->assertEquals(['data' => 'success', 'code' => 200], $result);
    }

    public function testExecute_cacheMiss_queriesDatabase(): void
    {
        $taskId = 'test-task-456';
        $this->procedure->taskId = $taskId;

        // 缓存中没有数据
        
        $dbResult = [
            'jsonrpc' => '2.0',
            'result' => ['message' => 'database result'],
            'id' => 'test'
        ];

        $asyncResult = new AsyncResult();
        $asyncResult->setTaskId($taskId);
        $asyncResult->setResult($dbResult);

        $this->resultRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['taskId' => $taskId])
            ->willReturn($asyncResult);

        $result = $this->procedure->execute();

        $this->assertEquals(['message' => 'database result'], $result);
    }

    public function testExecute_taskNotFound_throwsException(): void
    {
        $taskId = 'non-existent-task';
        $this->procedure->taskId = $taskId;

        // 缓存中没有数据，数据库也没有
        $this->resultRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['taskId' => $taskId])
            ->willReturn(null);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('未执行完成');
        $this->expectExceptionCode(-789);

        $this->procedure->execute();
    }

    public function testExecute_resultWithError_throwsApiException(): void
    {
        $taskId = 'error-task';
        $this->procedure->taskId = $taskId;

        $errorResult = [
            'jsonrpc' => '2.0',
            'error' => [
                'code' => -1001,
                'message' => 'Custom error message',
                'data' => ['details' => 'Error details']
            ],
            'id' => 'test'
        ];

        $this->cache->setTestData(AsyncResult::CACHE_PREFIX . $taskId, $errorResult);
        
        // 确保不会查询数据库
        $this->resultRepository->expects($this->never())
            ->method('findOneBy');

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Custom error message');
        $this->expectExceptionCode(-1001);

        $this->procedure->execute();
    }

    public function testExecute_resultWithErrorFromDatabase_throwsApiException(): void
    {
        $taskId = 'db-error-task';
        $this->procedure->taskId = $taskId;

        // 缓存中没有数据
        
        $errorResult = [
            'jsonrpc' => '2.0',
            'error' => [
                'code' => -999,
                'message' => 'Database error',
            ],
            'id' => 'test'
        ];

        $asyncResult = new AsyncResult();
        $asyncResult->setTaskId($taskId);
        $asyncResult->setResult($errorResult);

        $this->resultRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['taskId' => $taskId])
            ->willReturn($asyncResult);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Database error');
        $this->expectExceptionCode(-999);

        $this->procedure->execute();
    }

    public function testExecute_emptyResult_returnsEmptyArray(): void
    {
        $taskId = 'empty-task';
        $this->procedure->taskId = $taskId;

        $emptyResult = [
            'jsonrpc' => '2.0',
            'result' => null,
            'id' => 'test'
        ];

        $this->cache->setTestData(AsyncResult::CACHE_PREFIX . $taskId, $emptyResult);

        $result = $this->procedure->execute();

        $this->assertEquals([], $result);
    }

    public function testExecute_arrayResult_returnsCorrectly(): void
    {
        $taskId = 'array-task';
        $this->procedure->taskId = $taskId;

        $arrayResult = [
            'jsonrpc' => '2.0',
            'result' => [
                'items' => [1, 2, 3],
                'total' => 3,
                'status' => 'success'
            ],
            'id' => 'test'
        ];

        $this->cache->setTestData(AsyncResult::CACHE_PREFIX . $taskId, $arrayResult);

        $result = $this->procedure->execute();

        $this->assertEquals([
            'items' => [1, 2, 3],
            'total' => 3,
            'status' => 'success'
        ], $result);
    }

    public function testExecute_booleanResult_convertedToArray(): void
    {
        $taskId = 'boolean-task';
        $this->procedure->taskId = $taskId;

        $booleanResult = [
            'jsonrpc' => '2.0',
            'result' => true,
            'id' => 'test'
        ];

        $this->cache->setTestData(AsyncResult::CACHE_PREFIX . $taskId, $booleanResult);

        $result = $this->procedure->execute();

        $this->assertEquals([true], $result);
    }

    public function testExecute_stringResult_convertedToArray(): void
    {
        $taskId = 'string-task';
        $this->procedure->taskId = $taskId;

        $stringResult = [
            'jsonrpc' => '2.0',
            'result' => 'simple string result',
            'id' => 'test'
        ];

        $this->cache->setTestData(AsyncResult::CACHE_PREFIX . $taskId, $stringResult);

        $result = $this->procedure->execute();

        $this->assertEquals(['simple string result'], $result);
    }

    public function testTaskIdProperty_canBeSet(): void
    {
        $taskId = 'property-test';
        $this->procedure->taskId = $taskId;

        $this->assertEquals($taskId, $this->procedure->taskId);
    }
}
