<?php

namespace Tourze\JsonRPCAsyncBundle\Tests\Fixtures;

use Tourze\JsonRPC\Core\Contracts\RequestHandlerInterface;
use Tourze\JsonRPC\Core\Domain\JsonRpcMethodInterface;
use Tourze\JsonRPC\Core\Model\JsonRpcRequest;

class MockRequestHandler implements RequestHandlerInterface
{
    public function resolveMethod(JsonRpcRequest $request): JsonRpcMethodInterface
    {
        return match ($request->getMethod()) {
            'testMethod' => new TestServiceWithAsyncExecute(),
            'complexMethod' => new TestServiceWithAsyncExecute(),
            'emptyMethod' => new TestServiceWithAsyncExecute(),
            default => new TestServiceWithoutAsyncExecute(),
        };
    }
}
