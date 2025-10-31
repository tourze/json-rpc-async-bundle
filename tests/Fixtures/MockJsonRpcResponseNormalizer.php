<?php

namespace Tourze\JsonRPCAsyncBundle\Tests\Fixtures;

use Tourze\JsonRPC\Core\Model\JsonRpcResponse;

class MockJsonRpcResponseNormalizer
{
    public function normalize(JsonRpcResponse $response): ?array
    {
        return [
            'error' => [
                'code' => -1,
                'message' => 'Mock error message',
            ],
        ];
    }
}
