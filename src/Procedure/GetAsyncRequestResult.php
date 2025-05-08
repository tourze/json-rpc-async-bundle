<?php

namespace Tourze\JsonRPCAsyncBundle\Procedure;

use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\CacheItem;
use Symfony\Contracts\Cache\CacheInterface;
use Tourze\JsonRPC\Core\Attribute\MethodDoc;
use Tourze\JsonRPC\Core\Attribute\MethodExpose;
use Tourze\JsonRPC\Core\Attribute\MethodParam;
use Tourze\JsonRPC\Core\Exception\ApiException;
use Tourze\JsonRPC\Core\Procedure\BaseProcedure;
use Tourze\JsonRPCAsyncBundle\Entity\AsyncResult;
use Tourze\JsonRPCAsyncBundle\Repository\AsyncResultRepository;

#[MethodDoc('获取异步任务结果')]
#[MethodExpose('GetAsyncRequestResult')]
#[WithMonologChannel('procedure')]
class GetAsyncRequestResult extends BaseProcedure
{
    #[MethodParam('taskId')]
    public string $taskId;

    public function __construct(
        private readonly AsyncResultRepository $resultRepository,
        private readonly LoggerInterface $logger,
        private readonly CacheInterface $cache,
    ) {
    }

    public function execute(): array
    {
        $cache = null;
        try {
            /** @var CacheItem $cache */
            $cache = $this->cache->getItem(AsyncResult::CACHE_PREFIX . $this->taskId);
        } catch (\Throwable $exception) {
            $this->logger->error('从缓存中读取异步结果失败', [
                'exception' => $exception,
            ]);
        }
        if ($cache?->isHit()) {
            return $this->handleResult($cache->get());
        }

        $record = $this->resultRepository->findOneBy(['taskId' => $this->taskId]);
        if (!$record) {
            throw new ApiException('未执行完成', -789);
        }

        $result = $record->getResult();

        return $this->handleResult($result);
    }

    private function handleResult($result): array
    {
        if (isset($result['error'])) {
            throw new ApiException($result['error']['message'], $result['error']['code']);
        }

        return (array) $result['result'];
    }
}
