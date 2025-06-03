<?php

namespace Tourze\JsonRPCAsyncBundle\Message;

use Tourze\AsyncContracts\AsyncMessageInterface;

class AsyncProcedureMessage implements AsyncMessageInterface
{
    /**
     * @var string 任务ID
     */
    private string $taskId;

    /**
     * @var string JSON-RPC请求Payload
     */
    private string $payload;

    public function getTaskId(): string
    {
        return $this->taskId;
    }

    public function setTaskId(string $taskId): void
    {
        $this->taskId = $taskId;
    }

    public function getPayload(): string
    {
        return $this->payload;
    }

    public function setPayload(string $payload): void
    {
        $this->payload = $payload;
    }
}
