<?php

namespace Tourze\JsonRPCAsyncBundle\Tests\Message;

use PHPUnit\Framework\TestCase;
use Tourze\JsonRPCAsyncBundle\Message\AsyncProcedureMessage;

class AsyncProcedureMessageTest extends TestCase
{
    public function testSetAndGetTaskId_validValue(): void
    {
        $message = new AsyncProcedureMessage();
        $taskId = 'test-task-id-123';

        $message->setTaskId($taskId);
        $this->assertSame($taskId, $message->getTaskId());
    }

    public function testSetAndGetPayload_validValue(): void
    {
        $message = new AsyncProcedureMessage();
        $payload = '{"jsonrpc":"2.0","method":"test","params":{},"id":"1"}';

        $message->setPayload($payload);
        $this->assertSame($payload, $message->getPayload());
    }
}
