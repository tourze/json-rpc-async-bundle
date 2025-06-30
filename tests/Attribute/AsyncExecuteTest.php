<?php

namespace Tourze\JsonRPCAsyncBundle\Tests\Attribute;

use PHPUnit\Framework\TestCase;
use Tourze\JsonRPCAsyncBundle\Attribute\AsyncExecute;

class AsyncExecuteTest extends TestCase
{
    public function testAsyncExecuteAttribute_canBeInstantiated(): void
    {
        $attribute = new AsyncExecute();
        $this->assertInstanceOf(AsyncExecute::class, $attribute);
    }

    public function testAsyncExecuteAttribute_canBeUsedOnClass(): void
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
