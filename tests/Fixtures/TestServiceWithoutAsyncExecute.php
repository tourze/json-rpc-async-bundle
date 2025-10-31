<?php

namespace Tourze\JsonRPCAsyncBundle\Tests\Fixtures;

use Tourze\JsonRPC\Core\Domain\JsonRpcMethodInterface;
use Tourze\JsonRPC\Core\Model\JsonRpcRequest;

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
