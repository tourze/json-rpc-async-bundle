<?php

namespace Tourze\JsonRPCAsyncBundle\MessageHandler;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Tourze\JsonRPC\Core\Contracts\EndpointInterface;
use Tourze\JsonRPC\Core\Exception\JsonRpcException;
use Tourze\JsonRPC\Core\Model\JsonRpcResponse;
use Tourze\JsonRPCAsyncBundle\Entity\AsyncResult;
use Tourze\JsonRPCAsyncBundle\Message\AsyncProcedureMessage;
use Tourze\JsonRPCAsyncBundle\Repository\AsyncResultRepository;
use Tourze\JsonRPCEndpointBundle\Serialization\JsonRpcResponseNormalizer;

#[AsMessageHandler]
class AsyncProcedureHandler
{
    public function __construct(
        private readonly EndpointInterface $sdkEndpoint,
        private readonly EntityManagerInterface $entityManager,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
        private readonly JsonRpcResponseNormalizer $responseNormalizer,
        private readonly AsyncResultRepository $resultRepository,
    ) {
    }

    public function __invoke(AsyncProcedureMessage $message): void
    {
        // 如果已经能查到结果，那么不要重复执行
        $record = $this->resultRepository->findOneBy([
            'taskId' => $message->getTaskId(),
        ]);
        if ($record !== null) {
            $this->logger->warning('异步JSON-RPC任务已执行过，不允许重复执行', [
                'taskId' => $message->getTaskId(),
                'payload' => $message->getPayload(),
            ]);
            return;
        }

        $payload = $message->getPayload();

        try {
            $response = $this->sdkEndpoint->index($payload);
            $response = json_decode($response, true);
        } catch (\Throwable $exception) {
            $this->logger->error('异步执行时发生未知异常', [
                'taskId' => $message->getTaskId(),
                'payload' => $message->getPayload(),
                'exception' => $exception,
            ]);

            $j = new JsonRpcResponse();
            $j->setId($message->getTaskId());
            $j->setError(new JsonRpcException(-1, $exception->getMessage(), previous: $exception));
            $response = $this->responseNormalizer->normalize($j);
        }

        try {
            // 先把结果写缓存，以更早地读取到
            $this->cache->set(AsyncResult::CACHE_PREFIX . $message->getTaskId(), $response);
        } catch (\Throwable $exception) {
            $this->logger->error('提前写异步结果到缓存时失败', [
                'exception' => $exception,
                'taskId' => $message->getTaskId(),
            ]);
        }

        $record = new AsyncResult();
        $record->setTaskId($message->getTaskId());
        $record->setResult($response);
        $this->entityManager->persist($record);
        $this->entityManager->flush();
    }
}
