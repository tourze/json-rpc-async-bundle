<?php

declare(strict_types=1);

namespace Tourze\JsonRPCAsyncBundle\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\JsonRPCAsyncBundle\JsonRPCAsyncBundle;
use Tourze\PHPUnitSymfonyKernelTest\AbstractBundleTestCase;

/**
 * @internal
 */
#[CoversClass(JsonRPCAsyncBundle::class)]
#[RunTestsInSeparateProcesses]
final class JsonRPCAsyncBundleTest extends AbstractBundleTestCase
{
}
