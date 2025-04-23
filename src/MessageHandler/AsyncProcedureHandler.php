<?php

namespace Tourze\JsonRPCAsyncBundle\MessageHandler;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Tourze\JsonRPC\Core\Contracts\EndpointInterface;
use Tourze\JsonRPCAsyncBundle\Entity\AsyncResult;
use Tourze\JsonRPCAsyncBundle\Message\AsyncProcedureMessage;

#[AsMessageHandler]
class AsyncProcedureHandler
{
    public function __construct(
        private readonly EndpointInterface $sdkEndpoint,
        private readonly EntityManagerInterface $entityManager,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(AsyncProcedureMessage $message): void
    {
        $payload = $message->getPayload();
        $content = $this->sdkEndpoint->index($payload);
        $content = json_decode($content, true);

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
        $this->entityManager->persist($record);
        $this->entityManager->flush();
    }
}
