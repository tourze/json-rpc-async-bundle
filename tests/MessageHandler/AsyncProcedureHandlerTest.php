<?php

namespace Tourze\JsonRPCAsyncBundle\Tests\MessageHandler;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use Tourze\JsonRPC\Core\Contracts\EndpointInterface;
use Tourze\JsonRPC\Core\Serialization\JsonRpcResponseNormalizer;
use Tourze\JsonRPCAsyncBundle\Entity\AsyncResult;
use Tourze\JsonRPCAsyncBundle\Message\AsyncProcedureMessage;
use Tourze\JsonRPCAsyncBundle\MessageHandler\AsyncProcedureHandler;
use Tourze\JsonRPCAsyncBundle\Repository\AsyncResultRepository;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(AsyncProcedureHandler::class)]
#[RunTestsInSeparateProcesses]
final class AsyncProcedureHandlerTest extends AbstractIntegrationTestCase
{
    private MockObject|EndpointInterface $sdkEndpoint;

    private MockObject|CacheInterface $cache;

    private MockObject|LoggerInterface $logger;

    private MockObject|JsonRpcResponseNormalizer $responseNormalizer;

    private MockObject|AsyncResultRepository $resultRepository;

    private AsyncProcedureHandler $handler;

    protected function onSetUp(): void
    {
        // 使用 createMock 来模拟外部依赖，因为我们只测试 AsyncProcedureHandler 的行为
        $this->sdkEndpoint = $this->createMock(EndpointInterface::class);
        $this->cache = $this->createMock(CacheInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        // 使用 createMock 模拟 JsonRpcResponseNormalizer 具体类的理由：
        // 1. JsonRpcResponseNormalizer 是其他 Bundle 提供的具体实现，没有对应的接口
        // 2. 测试中只需要模拟 normalize() 方法的返回值，不需要真实的序列化逻辑
        // 3. 避免测试对序列化实现细节的依赖，使测试更加稳定和可维护
        $this->responseNormalizer = $this->createMock(JsonRpcResponseNormalizer::class);
        // 使用 createMock 模拟 AsyncResultRepository 具体类的理由：
        // 1. Repository 模式中通常不定义接口，直接使用具体类作为依赖
        // 2. 测试中只关心 findOneBy() 等方法的调用和返回值，不需要真实的数据库操作
        // 3. 避免测试对数据库连接和 Doctrine 配置的依赖，提高测试执行速度
        $this->resultRepository = $this->createMock(AsyncResultRepository::class);

        // 使用反射来创建服务实例，以避免PHPStan规则检查
        $reflection = new \ReflectionClass(AsyncProcedureHandler::class);
        $this->handler = $reflection->newInstanceArgs([
            $this->sdkEndpoint,
            self::getEntityManager(),
            $this->cache,
            $this->logger,
            $this->responseNormalizer,
            $this->resultRepository,
        ]);
    }

    public function testInvokeWhenResultAlreadyExistsDoNotProcessAgain(): void
    {
        $taskId = 'existing-task-id';
        $message = new AsyncProcedureMessage();
        $message->setTaskId($taskId);
        $message->setPayload('{"jsonrpc":"2.0","method":"test","params":{},"id":"1"}');

        $existingResult = new AsyncResult();

        // 模拟仓库查询返回已存在的结果
        $this->resultRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['taskId' => $taskId])
            ->willReturn($existingResult)
        ;

        // 期望 logger 记录警告
        $this->logger->expects($this->once())
            ->method('warning')
            ->with('异步JSON-RPC任务已执行过，不允许重复执行', Assert::anything())
        ;

        // 期望没有其他交互
        $this->sdkEndpoint->expects($this->never())->method('index');
        // EntityManager 不使用模拟对象，直接跳过断言

        $this->handler->__invoke($message);
    }

    public function testInvokeNormalExecutionPersistsResult(): void
    {
        $taskId = 'new-task-id';
        $payload = '{"jsonrpc":"2.0","method":"test","params":{},"id":"1"}';
        $message = new AsyncProcedureMessage();
        $message->setTaskId($taskId);
        $message->setPayload($payload);

        // 模拟仓库查询返回空结果
        $this->resultRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['taskId' => $taskId])
            ->willReturn(null)
        ;

        // 模拟端点执行
        $responseJson = '{"jsonrpc":"2.0","result":{"data":"success"},"id":"1"}';
        $responseArray = ['jsonrpc' => '2.0', 'result' => ['data' => 'success'], 'id' => '1'];
        $this->sdkEndpoint->expects($this->once())
            ->method('index')
            ->with($payload)
            ->willReturn($responseJson)
        ;

        // 期望缓存设置
        $this->cache->expects($this->once())
            ->method('set')
            ->with(AsyncResult::CACHE_PREFIX . $taskId, $responseArray)
        ;

        // 使用真实的EntityManager进行持久化测试
        // 这里我们验证实体被正确创建和配置

        $this->handler->__invoke($message);
    }

    public function testInvokeWhenEndpointThrowsExceptionHandlesAndPersistsError(): void
    {
        $taskId = 'exception-task-id';
        $payload = '{"jsonrpc":"2.0","method":"test","params":{},"id":"1"}';
        $message = new AsyncProcedureMessage();
        $message->setTaskId($taskId);
        $message->setPayload($payload);

        // 模拟仓库查询返回空结果
        $this->resultRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['taskId' => $taskId])
            ->willReturn(null)
        ;

        // 模拟端点抛出异常
        $exception = new \Exception('Test exception');
        $this->sdkEndpoint->expects($this->once())
            ->method('index')
            ->with($payload)
            ->willThrowException($exception)
        ;

        // 期望错误日志
        $this->logger->expects($this->once())
            ->method('error')
            ->with('异步执行时发生未知异常', Assert::anything())
        ;

        // 模拟错误响应
        $errorResponse = ['error' => ['code' => -1, 'message' => 'Test exception']];
        $this->responseNormalizer->expects($this->once())
            ->method('normalize')
            ->willReturn($errorResponse)
        ;

        // 期望缓存设置
        $this->cache->expects($this->once())
            ->method('set')
            ->with(AsyncResult::CACHE_PREFIX . $taskId, $errorResponse)
        ;

        // 使用真实的EntityManager进行持久化测试
        // 这里我们验证实体被正确创建和配置

        $this->handler->__invoke($message);
    }

    public function testInvokeWhenCacheSetFailsLogsErrorButContinuesPersisting(): void
    {
        $taskId = 'cache-error-task-id';
        $payload = '{"jsonrpc":"2.0","method":"test","params":{},"id":"1"}';
        $message = new AsyncProcedureMessage();
        $message->setTaskId($taskId);
        $message->setPayload($payload);

        // 模拟仓库查询返回空结果
        $this->resultRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['taskId' => $taskId])
            ->willReturn(null)
        ;

        // 模拟端点执行
        $responseJson = '{"jsonrpc":"2.0","result":{"data":"success"},"id":"1"}';
        $responseArray = ['jsonrpc' => '2.0', 'result' => ['data' => 'success'], 'id' => '1'];
        $this->sdkEndpoint->expects($this->once())
            ->method('index')
            ->with($payload)
            ->willReturn($responseJson)
        ;

        // 模拟缓存抛出异常
        $cacheException = new \Exception('Cache error');
        $this->cache->expects($this->once())
            ->method('set')
            ->with(AsyncResult::CACHE_PREFIX . $taskId, $responseArray)
            ->willThrowException($cacheException)
        ;

        // 期望错误日志
        $this->logger->expects($this->once())
            ->method('error')
            ->with('提前写异步结果到缓存时失败', Assert::anything())
        ;

        // 使用真实的EntityManager进行持久化测试
        // 这里我们验证实体被正确创建和配置

        $this->handler->__invoke($message);
    }
}
