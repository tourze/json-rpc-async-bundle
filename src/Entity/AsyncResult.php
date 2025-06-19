<?php

namespace Tourze\JsonRPCAsyncBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Tourze\DoctrineSnowflakeBundle\Service\SnowflakeIdGenerator;
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
    
    public const CACHE_PREFIX = 'async-result-';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(SnowflakeIdGenerator::class)]
    #[ORM\Column(type: Types::BIGINT, nullable: false, options: ['comment' => 'ID'])]
    private ?string $id = null;

    #[ORM\Column(type: Types::STRING, length: 100, unique: true, options: ['comment' => '任务id'])]
    private string $taskId;

    #[ORM\Column(type: Types::JSON, nullable: true, options: ['comment' => '响应内容'])]
    private ?array $result = null;

    public function __toString(): string
    {
        return sprintf('AsyncResult #%s - Task %s', $this->id ?? 'new', $this->taskId ?? 'unknown');
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getTaskId(): string
    {
        return $this->taskId;
    }

    public function setTaskId(string $taskId): void
    {
        $this->taskId = $taskId;
    }

    public function getResult(): ?array
    {
        return $this->result;
    }

    public function setResult(?array $result): void
    {
        $this->result = $result;
    }
    
    /**
     * 重写 setCreateTime 以支持 DateTimeInterface
     */
    public function setCreateTime(?\DateTimeInterface $createdAt): self
    {
        $this->createTime = $createdAt instanceof \DateTimeImmutable ? $createdAt : ($createdAt !== null ? \DateTimeImmutable::createFromInterface($createdAt) : null);

        return $this;
    }
}
