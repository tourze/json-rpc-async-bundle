<?php

namespace Tourze\JsonRPCAsyncBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\JsonRPCAsyncBundle\Exception\JsonEncodingException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(JsonEncodingException::class)]
final class JsonEncodingExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionCanBeInstantiated(): void
    {
        $exception = new JsonEncodingException('Test message');

        $this->assertInstanceOf(JsonEncodingException::class, $exception);
        $this->assertInstanceOf(\RuntimeException::class, $exception);
        $this->assertEquals('Test message', $exception->getMessage());
    }

    public function testExceptionCanBeInstantiatedWithCodeAndPrevious(): void
    {
        $previous = new \Exception('Previous exception');
        $exception = new JsonEncodingException('Test message', 500, $previous);

        $this->assertEquals('Test message', $exception->getMessage());
        $this->assertEquals(500, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }
}
