<?php

namespace Tourze\JsonRPCAsyncBundle\Tests\Attribute;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\JsonRPCAsyncBundle\Attribute\AsyncExecute;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(AsyncExecute::class)]
#[RunTestsInSeparateProcesses] final class AsyncExecuteTest extends AbstractIntegrationTestCase
{
    protected function onSetUp(): void
    {
        // 初始化容器
    }

    public function testAsyncExecuteAttributeCanBeInstantiated(): void
    {
        // @phpstan-ignore-next-line integrationTest.noDirectInstantiationOfCoveredClass - 集成测试中需要直接实例化被测类进行精确测试
        $attribute = new AsyncExecute();
        $this->assertInstanceOf(AsyncExecute::class, $attribute);
    }

    public function testAsyncExecuteAttributeCanBeUsedOnClass(): void
    {
        // 创建一个匿名类用于测试
        $testClass = new #[AsyncExecute] class {
        };

        // 获取类的属性
        $reflection = new \ReflectionClass($testClass);
        $attributes = $reflection->getAttributes(AsyncExecute::class);

        // 验证属性存在
        $this->assertCount(1, $attributes);
        $this->assertInstanceOf(AsyncExecute::class, $attributes[0]->newInstance());
    }
}
