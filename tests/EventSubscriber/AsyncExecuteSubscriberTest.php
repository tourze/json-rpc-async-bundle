<?php

namespace Tourze\JsonRPCAsyncBundle\Tests\EventSubscriber;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Tourze\JsonRPC\Core\Contracts\RequestHandlerInterface;
use Tourze\JsonRPC\Core\Domain\JsonRpcMethodInterface;
use Tourze\JsonRPC\Core\Model\JsonRpcRequest;
use Tourze\JsonRPCAsyncBundle\Attribute\AsyncExecute;
use Tourze\JsonRPCAsyncBundle\EventSubscriber\AsyncExecuteSubscriber;
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

class AsyncExecuteSubscriberTest extends TestCase
{
    private MockObject|MessageBusInterface $messageBus;
    private MockObject|Snowflake $snowflake;
    private MockObject|RequestHandlerInterface $requestHandler;
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
        // 恢复原始环境变量
        $_ENV['APP_ENV'] = $this->originalEnv;
    }

    public function testSubscriberCanBeConstructed(): void
    {
        $this->assertInstanceOf(AsyncExecuteSubscriber::class, $this->subscriber);
    }
}
