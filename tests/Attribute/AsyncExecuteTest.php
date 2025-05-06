<?php

namespace Tourze\JsonRPCAsyncBundle\Tests\Attribute;

use PHPUnit\Framework\TestCase;
use Tourze\JsonRPCAsyncBundle\Attribute\AsyncExecute;

// 用于测试的类
#[AsyncExecute]
class TestClassWithAttribute
{
}

class AsyncExecuteTest extends TestCase
{
    public function testAsyncExecuteAttribute_canBeInstantiated(): void
    {
        $attribute = new AsyncExecute();
        $this->assertInstanceOf(AsyncExecute::class, $attribute);
    }

    public function testAsyncExecuteAttribute_canBeUsedOnClass(): void
    {
        // 获取类的属性
        $reflection = new \ReflectionClass(TestClassWithAttribute::class);
        $attributes = $reflection->getAttributes(AsyncExecute::class);

        // 验证属性存在
        $this->assertCount(1, $attributes);
        $this->assertInstanceOf(AsyncExecute::class, $attributes[0]->newInstance());
    }
}
