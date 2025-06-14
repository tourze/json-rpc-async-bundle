<?php

namespace Tourze\JsonRPCAsyncBundle\Tests\Integration;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Tourze\IntegrationTestKernel\IntegrationTestKernel;
use Tourze\JsonRPCAsyncBundle\JsonRPCAsyncBundle;

class AsyncJsonRpcIntegrationTest extends TestCase
{
    public function testBundleExists(): void
    {
        $bundle = new JsonRPCAsyncBundle();
        $this->assertInstanceOf(JsonRPCAsyncBundle::class, $bundle);
    }

    protected static function getKernelClass(): string
    {
        return IntegrationTestKernel::class;
    }

    protected static function createKernel(array $options = []): IntegrationTestKernel
    {
        $appendBundles = [
            FrameworkBundle::class => ['all' => true],
            DoctrineBundle::class => ['all' => true],
            JsonRPCAsyncBundle::class => ['all' => true],
        ];
        
        $entityMappings = [
            'Tourze\JsonRPCAsyncBundle\Entity' => '/Users/air/work/source/php-monorepo/packages/json-rpc-async-bundle/tests/Integration/../../packages/json-rpc-async-bundle/src/Entity',
        ];

        return new IntegrationTestKernel(
            $options['environment'] ?? 'test',
            $options['debug'] ?? true,
            $appendBundles,
            $entityMappings
        );
    }
}
