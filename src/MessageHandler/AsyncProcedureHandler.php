<?php

namespace Tourze\JsonRPCAsyncBundle\MessageHandler;

use Doctrine\ORM\EntityManagerInterface;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Tourze\JsonRPC\Core\Contracts\EndpointInterface;
use Tourze\JsonRPC\Core\Exception\JsonRpcInternalErrorException;
use Tourze\JsonRPC\Core\Model\JsonRpcResponse;
use Tourze\JsonRPC\Core\Serialization\JsonRpcResponseNormalizer;
use Tourze\JsonRPCAsyncBundle\Entity\AsyncResult;
use Tourze\JsonRPCAsyncBundle\Message\AsyncProcedureMessage;
use Tourze\JsonRPCAsyncBundle\Repository\AsyncResultRepository;

#[AsMessageHandler]
#[WithMonologChannel(channel: 'json_rpc_async')]
readonly class AsyncProcedureHandler
{
    public function __construct(
        private EndpointInterface $sdkEndpoint,
        private EntityManagerInterface $entityManager,
        private CacheInterface $cache,
        private LoggerInterface $logger,
        private JsonRpcResponseNormalizer $responseNormalizer,
        private AsyncResultRepository $resultRepository,
    ) {
    }

    public function __invoke(AsyncProcedureMessage $message): void
    {
        // 如果已经能查到结果，那么不要重复执行
        $record = $this->resultRepository->findOneBy([
            'taskId' => $message->getTaskId(),
        ]);
        if (null !== $record) {
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
            $j->setError(new JsonRpcInternalErrorException($exception));
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
