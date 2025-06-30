<?php

namespace Tourze\JsonRPCAsyncBundle\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Tourze\JsonRPCAsyncBundle\JsonRPCAsyncBundle;

class JsonRPCAsyncBundleTest extends TestCase
{
    public function testBundleCanBeInstantiated(): void
    {
        $bundle = new JsonRPCAsyncBundle();
        $this->assertInstanceOf(JsonRPCAsyncBundle::class, $bundle);
        $this->assertInstanceOf(Bundle::class, $bundle);
    }

    public function testBundleHasCorrectName(): void
    {
        $bundle = new JsonRPCAsyncBundle();
        $this->assertEquals('JsonRPCAsyncBundle', $bundle->getName());
    }
}