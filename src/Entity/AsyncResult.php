<?php

namespace Tourze\JsonRPCAsyncBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Tourze\DoctrineIndexedBundle\Attribute\IndexColumn;
use Tourze\DoctrineSnowflakeBundle\Service\SnowflakeIdGenerator;
use Tourze\DoctrineTimestampBundle\Attribute\CreateTimeColumn;
use Tourze\JsonRPCAsyncBundle\Repository\AsyncResultRepository;
use Tourze\ScheduleEntityCleanBundle\Attribute\AsScheduleClean;

/**
 * jsonRpc 异步执行结果
 */
#[AsScheduleClean(expression: '22 */6 * * *', defaultKeepDay: 1, keepDayEnv: 'ASYNC_RESULT_PERSIST_DAY_NUM')]
#[ORM\Entity(repositoryClass: AsyncResultRepository::class)]
#[ORM\Table(name: 'async_json_rpc_result', options: ['comment' => 'jsonRpc 异步执行结果'])]
class AsyncResult
{
    public const CACHE_PREFIX = 'async-result-';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(SnowflakeIdGenerator::class)]
    #[ORM\Column(type: Types::BIGINT, nullable: false, options: ['comment' => 'ID'])]
    private ?string $id = '0';

    #[ORM\Column(type: Types::STRING, length: 100, unique: true, options: ['comment' => '任务id'])]
    private string $taskId;

    #[ORM\Column(type: Types::JSON, nullable: true, options: ['comment' => '响应内容'])]
    private ?array $result = null;

    #[IndexColumn]
    #[CreateTimeColumn]
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true, options: ['comment' => '创建时间'])]
    private ?\DateTimeInterface $createTime = null;

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

    public function setCreateTime(?\DateTimeInterface $createdAt): self
    {
        $this->createTime = $createdAt;

        return $this;
    }

    public function getCreateTime(): ?\DateTimeInterface
    {
        return $this->createTime;
    }
}
