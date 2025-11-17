<?php

namespace Tourze\JsonRPCAsyncBundle;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Tourze\BundleDependency\BundleDependencyInterface;
use Tourze\DoctrineSnowflakeBundle\DoctrineSnowflakeBundle;
use Tourze\DoctrineTimestampBundle\DoctrineTimestampBundle;
use Tourze\JsonRPCEndpointBundle\JsonRPCEndpointBundle;
use Tourze\ScheduleEntityCleanBundle\ScheduleEntityCleanBundle;

class JsonRPCAsyncBundle extends Bundle implements BundleDependencyInterface
{
    public static function getBundleDependencies(): array
    {
        return [
            DoctrineBundle::class => ['all' => true],
            DoctrineSnowflakeBundle::class => ['all' => true],
            DoctrineTimestampBundle::class => ['all' => true],
            ScheduleEntityCleanBundle::class => ['all' => true],
            JsonRPCEndpointBundle::class => ['all' => true],
        ];
    }
}
