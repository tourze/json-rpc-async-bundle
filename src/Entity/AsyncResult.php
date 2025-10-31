<?php

namespace Tourze\JsonRPCAsyncBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\DoctrineSnowflakeBundle\Traits\SnowflakeKeyAware;
use Tourze\DoctrineTimestampBundle\Traits\CreateTimeAware;
use Tourze\JsonRPCAsyncBundle\Repository\AsyncResultRepository;
use Tourze\ScheduleEntityCleanBundle\Attribute\AsScheduleClean;

/**
 * jsonRpc 异步执行结果
 */
#[AsScheduleClean(expression: '30 5 * * *', defaultKeepDay: 1, keepDayEnv: 'ASYNC_RESULT_PERSIST_DAY_NUM')]
#[ORM\Entity(repositoryClass: AsyncResultRepository::class)]
#[ORM\Table(name: 'async_json_rpc_result', options: ['comment' => 'jsonRpc 异步执行结果'])]
class AsyncResult implements \Stringable
{
    use CreateTimeAware;
    use SnowflakeKeyAware;

    public const CACHE_PREFIX = 'async-result-';

    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    #[ORM\Column(type: Types::STRING, length: 100, unique: true, options: ['comment' => '任务id'])]
    private string $taskId;

    /**
     * @var array<string, mixed>|null
     */
    #[Assert\Valid]
    #[ORM\Column(type: Types::JSON, nullable: true, options: ['comment' => '响应内容'])]
    private ?array $result = null;

    public function __toString(): string
    {
        return sprintf('AsyncResult #%s - Task %s', $this->id ?? 'new', $this->taskId ?? 'unknown');
    }

    public function getTaskId(): string
    {
        return $this->taskId;
    }

    public function setTaskId(string $taskId): void
    {
        $this->taskId = $taskId;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getResult(): ?array
    {
        return $this->result;
    }

    /**
     * @param array<string, mixed>|null $result
     */
    public function setResult(?array $result): void
    {
        $this->result = $result;
    }
}
