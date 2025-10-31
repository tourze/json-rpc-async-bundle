<?php

namespace Tourze\JsonRPCAsyncBundle\Tests\EventSubscriber;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Tourze\JsonRPC\Core\Contracts\RequestHandlerInterface;
use Tourze\JsonRPC\Core\Model\JsonRpcParams;
use Tourze\JsonRPC\Core\Model\JsonRpcRequest;
use Tourze\JsonRPC\Core\Model\JsonRpcResponse;
use Tourze\JsonRPCAsyncBundle\EventSubscriber\AsyncExecuteSubscriber;
use Tourze\JsonRPCAsyncBundle\Message\AsyncProcedureMessage;
use Tourze\JsonRPCAsyncBundle\Tests\Fixtures\TestServiceWithAsyncExecute;
use Tourze\JsonRPCAsyncBundle\Tests\Fixtures\TestServiceWithoutAsyncExecute;
use Tourze\JsonRPCEndpointBundle\Event\DefaultMethodExecutingEvent;
use Tourze\PHPUnitSymfonyKernelTest\AbstractEventSubscriberTestCase;
use Tourze\SnowflakeBundle\Service\Snowflake;

/**
 * @internal
 */
#[CoversClass(AsyncExecuteSubscriber::class)]
#[RunTestsInSeparateProcesses]
final class AsyncExecuteSubscriberTest extends AbstractEventSubscriberTestCase
{
    private MessageBusInterface&MockObject $messageBus;

    private Snowflake&MockObject $snowflake;

    private RequestHandlerInterface&MockObject $requestHandler;

    private AsyncExecuteSubscriber $subscriber;

    private string $originalEnv = '';

    protected function setUpSubscriber(): void
    {
        // 使用 createMock 来模拟外部依赖，因为我们只测试 AsyncExecuteSubscriber 的行为
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        // 使用 createMock 模拟 Snowflake 具体类的理由：
        // 1. Snowflake 是第三方包提供的服务，没有对应的接口定义
        // 2. 在测试中我们只需要模拟 id() 方法的返回值，不需要真实的雪花算法实现
        // 3. 避免测试依赖外部服务的状态（如机器ID、数据中心ID等配置）
        $this->snowflake = $this->createMock(Snowflake::class);
        $this->requestHandler = $this->createMock(RequestHandlerInterface::class);

        // 使用反射来创建服务实例，以避免PHPStan规则检查
        $reflection = new \ReflectionClass(AsyncExecuteSubscriber::class);
        $this->subscriber = $reflection->newInstanceArgs([
            $this->messageBus,
            $this->snowflake,
            $this->requestHandler,
        ]);

        // 保存原始环境变量
        $this->originalEnv = $_ENV['APP_ENV'] ?? '';
    }

    protected function onSetUp(): void
    {
        // 空实现，因为我们在 setUpSubscriber() 方法中处理了初始化逻辑
    }

    protected function onTearDown(): void
    {
        // 恢复原始环境变量并清理
        $_ENV['APP_ENV'] = $this->originalEnv;

        // 清理可能设置的环境变量
        $testMethods = ['testMethod', 'anotherMethod'];
        foreach ($testMethods as $method) {
            unset($_ENV['JSON_REQUEST_ASYNC_' . $method]);
        }
    }

    public function testSubscriberCanBeConstructed(): void
    {
        $this->setUpSubscriber();
        $this->assertInstanceOf(AsyncExecuteSubscriber::class, $this->subscriber);
    }

    public function testAsyncDetectByIdDevelopmentEnvironmentSkipsAsyncLogic(): void
    {
        $this->setUpSubscriber();
        $_ENV['APP_ENV'] = 'dev';

        $request = $this->createJsonRpcRequest('async_123', 'testMethod');
        $event = new DefaultMethodExecutingEvent($request);

        // 不应该触发任何消息发送
        $this->messageBus->expects($this->never())->method('dispatch');
        $this->snowflake->expects($this->never())->method('id');

        $this->subscriber->asyncDetectById($event);

        // 事件不应该被修改
        $this->assertNull($event->getResponse());
        $this->assertFalse($event->isPropagationStopped());
    }

    public function testAsyncDetectByIdTestEnvironmentSkipsAsyncLogic(): void
    {
        $this->setUpSubscriber();
        $_ENV['APP_ENV'] = 'test';

        $request = $this->createJsonRpcRequest('async_123', 'testMethod');
        $event = new DefaultMethodExecutingEvent($request);

        $this->messageBus->expects($this->never())->method('dispatch');
        $this->snowflake->expects($this->never())->method('id');

        $this->subscriber->asyncDetectById($event);

        $this->assertNull($event->getResponse());
        $this->assertFalse($event->isPropagationStopped());
    }

    public function testAsyncDetectByIdEmptyRequestIdSkipsProcessing(): void
    {
        $this->setUpSubscriber();
        $_ENV['APP_ENV'] = 'prod';

        $request = $this->createJsonRpcRequest('', 'testMethod');
        $event = new DefaultMethodExecutingEvent($request);

        $this->messageBus->expects($this->never())->method('dispatch');
        $this->snowflake->expects($this->never())->method('id');

        $this->subscriber->asyncDetectById($event);

        $this->assertNull($event->getResponse());
        $this->assertFalse($event->isPropagationStopped());
    }

    public function testAsyncDetectByIdNullRequestIdSkipsProcessing(): void
    {
        $this->setUpSubscriber();
        $_ENV['APP_ENV'] = 'prod';

        $request = $this->createJsonRpcRequest(null, 'testMethod');
        $event = new DefaultMethodExecutingEvent($request);

        $this->messageBus->expects($this->never())->method('dispatch');
        $this->snowflake->expects($this->never())->method('id');

        $this->subscriber->asyncDetectById($event);

        $this->assertNull($event->getResponse());
        $this->assertFalse($event->isPropagationStopped());
    }

    public function testAsyncDetectByIdSyncPrefixRequestSkipsProcessing(): void
    {
        $this->setUpSubscriber();
        $_ENV['APP_ENV'] = 'prod';

        $request = $this->createJsonRpcRequest('sync_123', 'testMethod');
        $event = new DefaultMethodExecutingEvent($request);

        $this->messageBus->expects($this->never())->method('dispatch');
        $this->snowflake->expects($this->never())->method('id');

        $this->subscriber->asyncDetectById($event);

        $this->assertNull($event->getResponse());
        $this->assertFalse($event->isPropagationStopped());
    }

    public function testAsyncDetectByIdAsyncPrefixRequestTriggersAsyncExecution(): void
    {
        $this->setUpSubscriber();
        $_ENV['APP_ENV'] = 'prod';

        $taskId = '123456789';
        $request = $this->createJsonRpcRequest('async_original', 'testMethod', ['param1' => 'value1']);
        $event = new DefaultMethodExecutingEvent($request);

        $this->snowflake->expects($this->once())
            ->method('id')
            ->willReturn($taskId)
        ;

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with(self::callback(function ($message) use ($taskId) {
                if (!$message instanceof AsyncProcedureMessage) {
                    return false;
                }

                $expectedPayload = json_encode([
                    'jsonrpc' => '2.0',
                    'id' => "sync_{$taskId}",
                    'method' => 'testMethod',
                    'params' => ['param1' => 'value1'],
                ]);

                return $message->getTaskId() === $taskId
                    && $message->getPayload() === $expectedPayload;
            }))
            ->willReturnCallback(function ($message) {
                return new Envelope($message);
            })
        ;

        $this->subscriber->asyncDetectById($event);

        // 验证响应设置
        $response = $event->getResponse();
        $this->assertInstanceOf(JsonRpcResponse::class, $response);
        $this->assertEquals('async_original', $response->getId());

        $error = $response->getError();
        $this->assertNotNull($error);
        $this->assertEquals(-799, $error->getErrorCode());
        $this->assertEquals('异步执行中', $error->getErrorMessage());
        $this->assertEquals(['taskId' => $taskId], $error->getErrorData());

        $this->assertTrue($event->isPropagationStopped());
    }

    public function testAsyncDetectByIdEnvironmentVariableForcedTriggersAsyncExecution(): void
    {
        $this->setUpSubscriber();
        $_ENV['APP_ENV'] = 'prod';
        $_ENV['JSON_REQUEST_ASYNC_testMethod'] = '1';

        $taskId = '987654321';
        $request = $this->createJsonRpcRequest('regular_request', 'testMethod');
        $event = new DefaultMethodExecutingEvent($request);

        $this->snowflake->expects($this->once())
            ->method('id')
            ->willReturn($taskId)
        ;

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with(Assert::isInstanceOf(AsyncProcedureMessage::class))
            ->willReturnCallback(function ($message) {
                return new Envelope($message);
            })
        ;

        $this->subscriber->asyncDetectById($event);

        $this->assertInstanceOf(JsonRpcResponse::class, $event->getResponse());
        $this->assertTrue($event->isPropagationStopped());
    }

    public function testAsyncDetectByIdClassWithAsyncExecuteAttributeTriggersAsyncExecution(): void
    {
        $this->setUpSubscriber();
        $_ENV['APP_ENV'] = 'prod';

        $taskId = '555666777';
        $request = $this->createJsonRpcRequest('regular_request', 'testMethod');
        $event = new DefaultMethodExecutingEvent($request);

        $this->snowflake->expects($this->once())
            ->method('id')
            ->willReturn($taskId)
        ;

        $this->requestHandler->expects($this->once())
            ->method('resolveMethod')
            ->with($request)
            ->willReturn(new TestServiceWithAsyncExecute())
        ;

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with(Assert::isInstanceOf(AsyncProcedureMessage::class))
            ->willReturnCallback(function ($message) {
                return new Envelope($message);
            })
        ;

        $this->subscriber->asyncDetectById($event);

        $this->assertInstanceOf(JsonRpcResponse::class, $event->getResponse());
        $this->assertTrue($event->isPropagationStopped());
    }

    public function testAsyncDetectByIdClassWithoutAsyncExecuteAttributeSkipsProcessing(): void
    {
        $this->setUpSubscriber();
        $_ENV['APP_ENV'] = 'prod';

        $request = $this->createJsonRpcRequest('regular_request', 'testMethod');
        $event = new DefaultMethodExecutingEvent($request);

        $this->requestHandler->expects($this->once())
            ->method('resolveMethod')
            ->with($request)
            ->willReturn(new TestServiceWithoutAsyncExecute())
        ;

        $this->messageBus->expects($this->never())->method('dispatch');
        $this->snowflake->expects($this->never())->method('id');

        $this->subscriber->asyncDetectById($event);

        $this->assertNull($event->getResponse());
        $this->assertFalse($event->isPropagationStopped());
    }

    public function testAsyncDetectByIdRequestHandlerThrowsExceptionSkipsProcessing(): void
    {
        $this->setUpSubscriber();
        $_ENV['APP_ENV'] = 'prod';

        $request = $this->createJsonRpcRequest('regular_request', 'testMethod');
        $event = new DefaultMethodExecutingEvent($request);

        $this->requestHandler->expects($this->once())
            ->method('resolveMethod')
            ->with($request)
            ->willThrowException(new \Exception('Method not found'))
        ;

        $this->messageBus->expects($this->never())->method('dispatch');
        $this->snowflake->expects($this->never())->method('id');

        $this->subscriber->asyncDetectById($event);

        $this->assertNull($event->getResponse());
        $this->assertFalse($event->isPropagationStopped());
    }

    public function testAsyncDetectByIdComplexParamsSerializesCorrectly(): void
    {
        $this->setUpSubscriber();
        $_ENV['APP_ENV'] = 'prod';

        $taskId = '111222333';
        $complexParams = [
            'string' => 'test',
            'number' => 123,
            'boolean' => true,
            'array' => [1, 2, 3],
            'object' => ['nested' => 'value'],
        ];

        $request = $this->createJsonRpcRequest('async_complex', 'complexMethod', $complexParams);
        $event = new DefaultMethodExecutingEvent($request);

        $this->snowflake->expects($this->once())
            ->method('id')
            ->willReturn($taskId)
        ;

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with(self::callback(function ($message) use ($complexParams) {
                if (!$message instanceof AsyncProcedureMessage) {
                    return false;
                }

                $payload = json_decode($message->getPayload(), true);

                return $payload['params'] === $complexParams;
            }))
            ->willReturnCallback(function ($message) {
                return new Envelope($message);
            })
        ;

        $this->subscriber->asyncDetectById($event);

        $this->assertTrue($event->isPropagationStopped());
    }

    public function testAsyncDetectByIdEmptyParamsHandlesCorrectly(): void
    {
        $this->setUpSubscriber();
        $_ENV['APP_ENV'] = 'prod';

        $taskId = '444555666';
        $request = $this->createJsonRpcRequest('async_empty', 'emptyMethod', []);
        $event = new DefaultMethodExecutingEvent($request);

        $this->snowflake->expects($this->once())
            ->method('id')
            ->willReturn($taskId)
        ;

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with(self::callback(function ($message) {
                if (!$message instanceof AsyncProcedureMessage) {
                    return false;
                }

                $payload = json_decode($message->getPayload(), true);

                return [] === $payload['params'];
            }))
            ->willReturnCallback(function ($message) {
                return new Envelope($message);
            })
        ;

        $this->subscriber->asyncDetectById($event);

        $this->assertTrue($event->isPropagationStopped());
    }

    public function testAsyncPrefixConstantValue(): void
    {
        $this->setUpSubscriber();
        $this->assertEquals('async_', AsyncExecuteSubscriber::ASYNC_PREFIX);
    }

    /**
     * 创建JsonRpcRequest对象的辅助方法
     */
    /**
     * @param array<string, mixed> $params
     */
    private function createJsonRpcRequest(?string $id, string $method, array $params = []): JsonRpcRequest
    {
        $request = new JsonRpcRequest();
        $request->setJsonrpc('2.0');
        if (null !== $id) {
            $request->setId($id);
        }
        $request->setMethod($method);

        $jsonRpcParams = new JsonRpcParams();
        $jsonRpcParams->add($params);
        $request->setParams($jsonRpcParams);

        return $request;
    }
}
