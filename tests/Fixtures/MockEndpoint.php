<?php

namespace Tourze\JsonRPCAsyncBundle\Tests\Fixtures;

use Symfony\Component\HttpFoundation\Request;
use Tourze\JsonRPC\Core\Contracts\EndpointInterface;

class MockEndpoint implements EndpointInterface
{
    public function index(string $payload, ?Request $request = null): string
    {
        return '{"jsonrpc":"2.0","result":{"data":"test_result"},"id":"1"}';
    }
}
