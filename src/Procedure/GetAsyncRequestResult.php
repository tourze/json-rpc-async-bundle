<?php

namespace Tourze\JsonRPCAsyncBundle\Procedure;

use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use Tourze\JsonRPC\Core\Attribute\MethodDoc;
use Tourze\JsonRPC\Core\Attribute\MethodExpose;
use Tourze\JsonRPC\Core\Attribute\MethodParam;
use Tourze\JsonRPC\Core\Attribute\MethodTag;
use Tourze\JsonRPC\Core\Exception\ApiException;
use Tourze\JsonRPC\Core\Procedure\BaseProcedure;
use Tourze\JsonRPCAsyncBundle\Entity\AsyncResult;
use Tourze\JsonRPCAsyncBundle\Repository\AsyncResultRepository;

#[MethodDoc(summary: '获取异步任务结果')]
#[MethodExpose(method: 'GetAsyncRequestResult')]
#[MethodTag(name: '异步任务')]
#[WithMonologChannel(channel: 'json_rpc_async')]
class GetAsyncRequestResult extends BaseProcedure
{
    #[MethodParam(description: 'taskId')]
    public string $taskId;

    public function __construct(
        private readonly AsyncResultRepository $resultRepository,
        private readonly LoggerInterface $logger,
        private readonly CacheInterface $cache,
    ) {
    }

    public function execute(): array
    {
        $cacheKey = AsyncResult::CACHE_PREFIX . $this->taskId;

        $cachedResult = null;
        try {
            $cachedResult = $this->cache->get($cacheKey);
        } catch (\Throwable $exception) {
            $this->logger->error('从缓存中读取异步结果失败', [
                'exception' => $exception,
            ]);
        }

        if (null !== $cachedResult) {
            return $this->handleResult($cachedResult);
        }

        $record = $this->resultRepository->findOneBy(['taskId' => $this->taskId]);
        if (null === $record) {
            throw new ApiException('未执行完成', -789);
        }

        $result = $record->getResult();

        return $this->handleResult($result);
    }

    /**
     * @param array<string, mixed>|null $result
     * @return array<string, mixed>
     */
    private function handleResult(?array $result): array
    {
        if (null === $result) {
            return [];
        }

        if (isset($result['error'])) {
            throw new ApiException($result['error']['message'], $result['error']['code']);
        }

        return (array) ($result['result'] ?? []);
    }
}
