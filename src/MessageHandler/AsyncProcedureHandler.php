<?php

namespace Tourze\JsonRPCAsyncBundle\MessageHandler;

use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Tourze\JsonRPC\Core\Contracts\EndpointInterface;
use Tourze\JsonRPCAsyncBundle\Entity\AsyncResult;
use Tourze\JsonRPCAsyncBundle\Message\AsyncProcedureMessage;
use Tourze\JsonRPCAsyncBundle\Repository\AsyncResultRepository;
use Yiisoft\Json\Json;

#[AsMessageHandler]
class AsyncProcedureHandler
{
    public function __construct(
        private readonly EndpointInterface $sdkEndpoint,
        private readonly AsyncResultRepository $resultRepository,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(AsyncProcedureMessage $message): void
    {
        $payload = $message->getPayload();
        $content = $this->sdkEndpoint->index($payload);
        $content = Json::decode($content);

        try {
            // 先把结果写缓存，以更早地读取到
            $this->cache->set(AsyncResult::CACHE_PREFIX . $message->getTaskId(), $content);
        } catch (\Throwable $exception) {
            $this->logger->error('提前写异步结果到缓存时失败', [
                'exception' => $exception,
                'taskId' => $message->getTaskId(),
            ]);
        }

        $record = new AsyncResult();
        $record->setTaskId($message->getTaskId());
        $record->setResult($content);
        $this->resultRepository->save($record);
    }
}
