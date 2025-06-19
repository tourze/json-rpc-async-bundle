<?php

namespace Tourze\JsonRPCAsyncBundle\Tests\EventSubscriber;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Tourze\JsonRPC\Core\Contracts\RequestHandlerInterface;
use Tourze\JsonRPC\Core\Domain\JsonRpcMethodInterface;
use Tourze\JsonRPC\Core\Event\MethodExecutingEvent;
use Tourze\JsonRPC\Core\Model\JsonRpcParams;
use Tourze\JsonRPC\Core\Model\JsonRpcRequest;
use Tourze\JsonRPC\Core\Model\JsonRpcResponse;
use Tourze\JsonRPCAsyncBundle\Attribute\AsyncExecute;
use Tourze\JsonRPCAsyncBundle\EventSubscriber\AsyncExecuteSubscriber;
use Tourze\JsonRPCAsyncBundle\Message\AsyncProcedureMessage;
use Tourze\SnowflakeBundle\Service\Snowflake;

// 创建一个带有AsyncExecute属性的测试类
#[AsyncExecute]
class TestServiceWithAsyncExecute implements JsonRpcMethodInterface
{
    public function execute(): array
    {
        return [];
    }

    public function __invoke(JsonRpcRequest $request): mixed
    {
        return $this->execute();
    }
}

// 创建一个没有AsyncExecute属性的测试类
class TestServiceWithoutAsyncExecute implements JsonRpcMethodInterface
{
    public function execute(): array
    {
        return [];
    }

    public function __invoke(JsonRpcRequest $request): mixed
    {
        return $this->execute();
    }
}

class AsyncExecuteSubscriberTest extends TestCase
{
    private MessageBusInterface&MockObject $messageBus;
    private Snowflake&MockObject $snowflake;
    private RequestHandlerInterface&MockObject $requestHandler;
    private AsyncExecuteSubscriber $subscriber;
    private string $originalEnv;

    protected function setUp(): void
    {
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->snowflake = $this->createMock(Snowflake::class);
        $this->requestHandler = $this->createMock(RequestHandlerInterface::class);

        $this->subscriber = new AsyncExecuteSubscriber(
            $this->messageBus,
            $this->snowflake,
            $this->requestHandler
        );

        // 保存原始环境变量
        $this->originalEnv = $_ENV['APP_ENV'] ?? '';
    }

    protected function tearDown(): void
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
        $this->assertInstanceOf(AsyncExecuteSubscriber::class, $this->subscriber);
    }

    public function testAsyncDetectById_developmentEnvironment_skipsAsyncLogic(): void
    {
        $_ENV['APP_ENV'] = 'dev';
        
        $request = $this->createJsonRpcRequest('async_123', 'testMethod');
        $event = new MethodExecutingEvent();
        $event->setItem($request);

        // 不应该触发任何消息发送
        $this->messageBus->expects($this->never())->method('dispatch');
        $this->snowflake->expects($this->never())->method('id');

        $this->subscriber->asyncDetectById($event);

        // 事件不应该被修改
        $this->assertNull($event->getResponse());
        $this->assertFalse($event->isPropagationStopped());
    }

    public function testAsyncDetectById_testEnvironment_skipsAsyncLogic(): void
    {
        $_ENV['APP_ENV'] = 'test';
        
        $request = $this->createJsonRpcRequest('async_123', 'testMethod');
        $event = new MethodExecutingEvent();
        $event->setItem($request);

        $this->messageBus->expects($this->never())->method('dispatch');
        $this->snowflake->expects($this->never())->method('id');

        $this->subscriber->asyncDetectById($event);

        $this->assertNull($event->getResponse());
        $this->assertFalse($event->isPropagationStopped());
    }

    public function testAsyncDetectById_emptyRequestId_skipsProcessing(): void
    {
        $_ENV['APP_ENV'] = 'prod';
        
        $request = $this->createJsonRpcRequest('', 'testMethod');
        $event = new MethodExecutingEvent();
        $event->setItem($request);

        $this->messageBus->expects($this->never())->method('dispatch');
        $this->snowflake->expects($this->never())->method('id');

        $this->subscriber->asyncDetectById($event);

        $this->assertNull($event->getResponse());
        $this->assertFalse($event->isPropagationStopped());
    }

    public function testAsyncDetectById_nullRequestId_skipsProcessing(): void
    {
        $_ENV['APP_ENV'] = 'prod';
        
        $request = $this->createJsonRpcRequest(null, 'testMethod');
        $event = new MethodExecutingEvent();
        $event->setItem($request);

        $this->messageBus->expects($this->never())->method('dispatch');
        $this->snowflake->expects($this->never())->method('id');

        $this->subscriber->asyncDetectById($event);

        $this->assertNull($event->getResponse());
        $this->assertFalse($event->isPropagationStopped());
    }

    public function testAsyncDetectById_syncPrefixRequest_skipsProcessing(): void
    {
        $_ENV['APP_ENV'] = 'prod';
        
        $request = $this->createJsonRpcRequest('sync_123', 'testMethod');
        $event = new MethodExecutingEvent();
        $event->setItem($request);

        $this->messageBus->expects($this->never())->method('dispatch');
        $this->snowflake->expects($this->never())->method('id');

        $this->subscriber->asyncDetectById($event);

        $this->assertNull($event->getResponse());
        $this->assertFalse($event->isPropagationStopped());
    }

    public function testAsyncDetectById_asyncPrefixRequest_triggersAsyncExecution(): void
    {
        $_ENV['APP_ENV'] = 'prod';
        
        $taskId = '123456789';
        $request = $this->createJsonRpcRequest('async_original', 'testMethod', ['param1' => 'value1']);
        $event = new MethodExecutingEvent();
        $event->setItem($request);

        $this->snowflake->expects($this->once())
            ->method('id')
            ->willReturn($taskId);

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($message) use ($taskId) {
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
            });

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

    public function testAsyncDetectById_environmentVariableForced_triggersAsyncExecution(): void
    {
        $_ENV['APP_ENV'] = 'prod';
        $_ENV['JSON_REQUEST_ASYNC_testMethod'] = '1';
        
        $taskId = '987654321';
        $request = $this->createJsonRpcRequest('regular_request', 'testMethod');
        $event = new MethodExecutingEvent();
        $event->setItem($request);

        $this->snowflake->expects($this->once())
            ->method('id')
            ->willReturn($taskId);

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(AsyncProcedureMessage::class))
            ->willReturnCallback(function ($message) {
                return new Envelope($message);
            });

        $this->subscriber->asyncDetectById($event);

        $this->assertInstanceOf(JsonRpcResponse::class, $event->getResponse());
        $this->assertTrue($event->isPropagationStopped());
    }

    public function testAsyncDetectById_classWithAsyncExecuteAttribute_triggersAsyncExecution(): void
    {
        $_ENV['APP_ENV'] = 'prod';
        
        $taskId = '555666777';
        $request = $this->createJsonRpcRequest('regular_request', 'testMethod');
        $event = new MethodExecutingEvent();
        $event->setItem($request);

        $this->snowflake->expects($this->once())
            ->method('id')
            ->willReturn($taskId);

        $this->requestHandler->expects($this->once())
            ->method('resolveMethod')
            ->with($request)
            ->willReturn(new TestServiceWithAsyncExecute());

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(AsyncProcedureMessage::class))
            ->willReturnCallback(function ($message) {
                return new Envelope($message);
            });

        $this->subscriber->asyncDetectById($event);

        $this->assertInstanceOf(JsonRpcResponse::class, $event->getResponse());
        $this->assertTrue($event->isPropagationStopped());
    }

    public function testAsyncDetectById_classWithoutAsyncExecuteAttribute_skipsProcessing(): void
    {
        $_ENV['APP_ENV'] = 'prod';
        
        $request = $this->createJsonRpcRequest('regular_request', 'testMethod');
        $event = new MethodExecutingEvent();
        $event->setItem($request);

        $this->requestHandler->expects($this->once())
            ->method('resolveMethod')
            ->with($request)
            ->willReturn(new TestServiceWithoutAsyncExecute());

        $this->messageBus->expects($this->never())->method('dispatch');
        $this->snowflake->expects($this->never())->method('id');

        $this->subscriber->asyncDetectById($event);

        $this->assertNull($event->getResponse());
        $this->assertFalse($event->isPropagationStopped());
    }

    public function testAsyncDetectById_requestHandlerThrowsException_skipsProcessing(): void
    {
        $_ENV['APP_ENV'] = 'prod';
        
        $request = $this->createJsonRpcRequest('regular_request', 'testMethod');
        $event = new MethodExecutingEvent();
        $event->setItem($request);

        $this->requestHandler->expects($this->once())
            ->method('resolveMethod')
            ->with($request)
            ->willThrowException(new \Exception('Method not found'));

        $this->messageBus->expects($this->never())->method('dispatch');
        $this->snowflake->expects($this->never())->method('id');

        $this->subscriber->asyncDetectById($event);

        $this->assertNull($event->getResponse());
        $this->assertFalse($event->isPropagationStopped());
    }

    public function testAsyncDetectById_complexParams_serializesCorrectly(): void
    {
        $_ENV['APP_ENV'] = 'prod';
        
        $taskId = '111222333';
        $complexParams = [
            'string' => 'test',
            'number' => 123,
            'boolean' => true,
            'array' => [1, 2, 3],
            'object' => ['nested' => 'value']
        ];
        
        $request = $this->createJsonRpcRequest('async_complex', 'complexMethod', $complexParams);
        $event = new MethodExecutingEvent();
        $event->setItem($request);

        $this->snowflake->expects($this->once())
            ->method('id')
            ->willReturn($taskId);

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($message) use ($complexParams) {
                if (!$message instanceof AsyncProcedureMessage) {
                    return false;
                }
                
                $payload = json_decode($message->getPayload(), true);
                return $payload['params'] === $complexParams;
            }))
            ->willReturnCallback(function ($message) {
                return new Envelope($message);
            });

        $this->subscriber->asyncDetectById($event);

        $this->assertTrue($event->isPropagationStopped());
    }

    public function testAsyncDetectById_emptyParams_handlesCorrectly(): void
    {
        $_ENV['APP_ENV'] = 'prod';
        
        $taskId = '444555666';
        $request = $this->createJsonRpcRequest('async_empty', 'emptyMethod', []);
        $event = new MethodExecutingEvent();
        $event->setItem($request);

        $this->snowflake->expects($this->once())
            ->method('id')
            ->willReturn($taskId);

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($message) {
                if (!$message instanceof AsyncProcedureMessage) {
                    return false;
                }
                
                $payload = json_decode($message->getPayload(), true);
                return $payload['params'] === [];
            }))
            ->willReturnCallback(function ($message) {
                return new Envelope($message);
            });

        $this->subscriber->asyncDetectById($event);

        $this->assertTrue($event->isPropagationStopped());
    }

    public function testAsyncPrefix_constantValue(): void
    {
        $this->assertEquals('async_', AsyncExecuteSubscriber::ASYNC_PREFIX);
    }

    /**
     * 创建JsonRpcRequest对象的辅助方法
     */
    private function createJsonRpcRequest(?string $id, string $method, array $params = []): JsonRpcRequest
    {
        $request = new JsonRpcRequest();
        $request->setJsonrpc('2.0');
        if ($id !== null) {
            $request->setId($id);
        }
        $request->setMethod($method);
        
        $jsonRpcParams = new JsonRpcParams();
        $jsonRpcParams->add($params);
        $request->setParams($jsonRpcParams);
        
        return $request;
    }
}
