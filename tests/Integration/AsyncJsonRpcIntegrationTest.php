<?php

namespace Tourze\JsonRPCAsyncBundle\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Tourze\JsonRPCAsyncBundle\JsonRPCAsyncBundle;

class AsyncJsonRpcIntegrationTest extends TestCase
{
    public function testBundleExists(): void
    {
        $bundle = new JsonRPCAsyncBundle();
        $this->assertInstanceOf(JsonRPCAsyncBundle::class, $bundle);
    }
}
