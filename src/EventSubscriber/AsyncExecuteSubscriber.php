<?php

namespace Tourze\JsonRPCAsyncBundle\EventSubscriber;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\MessageBusInterface;
use Tourze\DoctrineHelper\ReflectionHelper;
use Tourze\DoctrineSnowflakeBundle\Service\Snowflake;
use Tourze\JsonRPC\Core\Contracts\RequestHandlerInterface;
use Tourze\JsonRPC\Core\Event\MethodExecutingEvent;
use Tourze\JsonRPC\Core\Exception\ApiException;
use Tourze\JsonRPC\Core\Model\JsonRpcRequest;
use Tourze\JsonRPC\Core\Model\JsonRpcResponse;
use Tourze\JsonRPCAsyncBundle\Attribute\AsyncExecute;
use Tourze\JsonRPCAsyncBundle\Message\AsyncProcedureMessage;

/**
 * 异步执行逻辑的处理
 * 要注意这个需要前端配合才能真正使用
 */
class AsyncExecuteSubscriber
{
    public const ASYNC_PREFIX = 'async_';

    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly Snowflake $snowflake,
        private readonly RequestHandlerInterface $requestHandler,
    ) {
    }

    #[AsEventListener(priority: 999)]
    public function asyncDetectById(MethodExecutingEvent $event): void
    {
        if (!$this->checkRequest($event->getItem())) {
            return;
        }

        $taskId = $this->snowflake->id();

        $message = new AsyncProcedureMessage();
        $message->setTaskId($taskId);
        $message->setPayload(json_encode([
            'jsonrpc' => $event->getItem()->getJsonrpc(),
            'id' => "sync_{$taskId}",
            'method' => $event->getItem()->getMethod(),
            'params' => $event->getItem()->getParams()->toArray(),
        ]));
        $this->messageBus->dispatch($message);

        // 补充一个返回结果
        $response = new JsonRpcResponse();
        $response->setId($event->getItem()->getId());
        $error = new ApiException('异步执行中', -799, [
            'taskId' => $taskId,
        ]);
        $response->setError($error);
        $event->setResponse($response);
        $event->stopPropagation(); // 停止冒泡
    }

    /**
     * 判断是否需要异步执行
     */
    private function checkRequest(JsonRpcRequest $request): bool
    {
        // 如果是开发环境，我们直接跳过这个异步逻辑
        if ('prod' !== $_ENV['APP_ENV']) {
            return false;
        }

        // 没ID，不处理
        if (empty($request->getId())) {
            return false;
        }
        // 直接声明为同步的话，我们不处理
        if (str_starts_with($request->getId(), 'sync_')) {
            return false;
        }
        // 异步的处理
        if (str_starts_with($request->getId(), static::ASYNC_PREFIX)) {
            return true;
        }
        // 强制声明的
        if (isset($_ENV['JSON_REQUEST_ASYNC_' . $request->getMethod()])) {
            return true;
        }
        try {
            $service = $this->requestHandler->resolveMethod($request);

            return ReflectionHelper::hasClassAttributes(ReflectionHelper::getClassReflection($service), AsyncExecute::class);
        } catch (\Throwable $exception) {
        }

        return false;
    }
}
