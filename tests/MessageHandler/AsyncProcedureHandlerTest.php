<?php

namespace Tourze\JsonRPCAsyncBundle\Tests\MessageHandler;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use Tourze\JsonRPC\Core\Contracts\EndpointInterface;
use Tourze\JsonRPCAsyncBundle\Entity\AsyncResult;
use Tourze\JsonRPCAsyncBundle\Message\AsyncProcedureMessage;
use Tourze\JsonRPCAsyncBundle\MessageHandler\AsyncProcedureHandler;
use Tourze\JsonRPCAsyncBundle\Repository\AsyncResultRepository;
use Tourze\JsonRPCEndpointBundle\Serialization\JsonRpcResponseNormalizer;

class AsyncProcedureHandlerTest extends TestCase
{
    private MockObject|EndpointInterface $sdkEndpoint;
    private MockObject|EntityManagerInterface $entityManager;
    private MockObject|CacheInterface $cache;
    private MockObject|LoggerInterface $logger;
    private MockObject|JsonRpcResponseNormalizer $responseNormalizer;
    private MockObject|AsyncResultRepository $resultRepository;
    private AsyncProcedureHandler $handler;

    protected function setUp(): void
    {
        $this->sdkEndpoint = $this->createMock(EndpointInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->cache = $this->createMock(CacheInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->responseNormalizer = $this->createMock(JsonRpcResponseNormalizer::class);
        $this->resultRepository = $this->createMock(AsyncResultRepository::class);

        $this->handler = new AsyncProcedureHandler(
            $this->sdkEndpoint,
            $this->entityManager,
            $this->cache,
            $this->logger,
            $this->responseNormalizer,
            $this->resultRepository
        );
    }

    public function testInvoke_whenResultAlreadyExists_doNotProcessAgain(): void
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
            ->willReturn($existingResult);

        // 期望 logger 记录警告
        $this->logger->expects($this->once())
            ->method('warning')
            ->with('异步JSON-RPC任务已执行过，不允许重复执行', $this->anything());

        // 期望没有其他交互
        $this->sdkEndpoint->expects($this->never())->method('index');
        $this->entityManager->expects($this->never())->method('persist');
        $this->entityManager->expects($this->never())->method('flush');

        $this->handler->__invoke($message);
    }

    public function testInvoke_normalExecution_persistsResult(): void
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
            ->willReturn(null);

        // 模拟端点执行
        $responseJson = '{"jsonrpc":"2.0","result":{"data":"success"},"id":"1"}';
        $responseArray = ['jsonrpc' => '2.0', 'result' => ['data' => 'success'], 'id' => '1'];
        $this->sdkEndpoint->expects($this->once())
            ->method('index')
            ->with($payload)
            ->willReturn($responseJson);

        // 期望缓存设置
        $this->cache->expects($this->once())
            ->method('set')
            ->with(AsyncResult::CACHE_PREFIX . $taskId, $responseArray);

        // 期望持久化
        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->callback(function ($entity) use ($taskId, $responseArray) {
                return $entity instanceof AsyncResult
                    && $entity->getTaskId() === $taskId
                    && $entity->getResult() === $responseArray;
            }));

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->handler->__invoke($message);
    }

    public function testInvoke_whenEndpointThrowsException_handlesAndPersistsError(): void
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
            ->willReturn(null);

        // 模拟端点抛出异常
        $exception = new \Exception('Test exception');
        $this->sdkEndpoint->expects($this->once())
            ->method('index')
            ->with($payload)
            ->willThrowException($exception);

        // 期望错误日志
        $this->logger->expects($this->once())
            ->method('error')
            ->with('异步执行时发生未知异常', $this->anything());

        // 模拟错误响应
        $errorResponse = ['error' => ['code' => -1, 'message' => 'Test exception']];
        $this->responseNormalizer->expects($this->once())
            ->method('normalize')
            ->willReturn($errorResponse);

        // 期望缓存设置
        $this->cache->expects($this->once())
            ->method('set')
            ->with(AsyncResult::CACHE_PREFIX . $taskId, $errorResponse);

        // 期望持久化
        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->callback(function ($entity) use ($errorResponse, $taskId) {
                return $entity instanceof AsyncResult
                    && $entity->getTaskId() === $taskId
                    && $entity->getResult() === $errorResponse;
            }));

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->handler->__invoke($message);
    }

    public function testInvoke_whenCacheSetFails_logsErrorButContinuesPersisting(): void
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
            ->willReturn(null);

        // 模拟端点执行
        $responseJson = '{"jsonrpc":"2.0","result":{"data":"success"},"id":"1"}';
        $responseArray = ['jsonrpc' => '2.0', 'result' => ['data' => 'success'], 'id' => '1'];
        $this->sdkEndpoint->expects($this->once())
            ->method('index')
            ->with($payload)
            ->willReturn($responseJson);

        // 模拟缓存抛出异常
        $cacheException = new \Exception('Cache error');
        $this->cache->expects($this->once())
            ->method('set')
            ->with(AsyncResult::CACHE_PREFIX . $taskId, $responseArray)
            ->willThrowException($cacheException);

        // 期望错误日志
        $this->logger->expects($this->once())
            ->method('error')
            ->with('提前写异步结果到缓存时失败', $this->anything());

        // 期望持久化依然执行
        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(AsyncResult::class));

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->handler->__invoke($message);
    }
}
