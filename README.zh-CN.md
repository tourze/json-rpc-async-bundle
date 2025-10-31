# JsonRPC 异步扩展包

[English](README.md) | [中文](README.zh-CN.md)

[![PHP 版本](https://img.shields.io/packagist/dependency-v/tourze/json-rpc-async-bundle/php?style=flat-square)](https://packagist.org/packages/tourze/json-rpc-async-bundle)
[![许可证](https://img.shields.io/packagist/l/tourze/json-rpc-async-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/json-rpc-async-bundle)
[![最新版本](https://img.shields.io/packagist/v/tourze/json-rpc-async-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/json-rpc-async-bundle)
[![构建状态](https://img.shields.io/travis/tourze/json-rpc-async-bundle/master.svg?style=flat-square)](https://travis-ci.org/tourze/json-rpc-async-bundle)
[![质量评分](https://img.shields.io/scrutinizer/g/tourze/json-rpc-async-bundle.svg?style=flat-square)](https://scrutinizer-ci.com/g/tourze/json-rpc-async-bundle)
[![代码覆盖率](https://img.shields.io/scrutinizer/coverage/g/tourze/json-rpc-async-bundle.svg?style=flat-square)](https://scrutinizer-ci.com/g/tourze/json-rpc-async-bundle)
[![总下载量](https://img.shields.io/packagist/dt/tourze/json-rpc-async-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/json-rpc-async-bundle)

一个用于处理 JSON-RPC 异步请求与结果的 Symfony 扩展包，
支持将耗时/重型的 JSON-RPC 调用异步执行，并可通过任务ID查询执行结果。

## 目录

- [功能特性](#功能特性)
- [安装说明](#安装说明)
- [快速开始](#快速开始)
- [高级用法](#高级用法)
- [详细文档](#详细文档)
- [贡献指南](#贡献指南)
- [许可证](#许可证)
- [更新日志](#更新日志)

## 功能特性

- JSON-RPC 方法异步执行
- 基于任务ID查询结果
- 使用 Doctrine ORM 持久化异步结果
- 优先缓存读取结果，提升查询效率
- 集成 Symfony Messenger 与雪花ID生成
- 自动清理过期结果

## 安装说明

### 环境要求

- PHP >= 8.1
- Symfony >= 6.4
- Doctrine ORM >= 3.0
- Symfony Messenger 用于异步任务处理

### Composer 安装

```bash
composer require tourze/json-rpc-async-bundle
```

### 启用 Bundle

如未自动注册，请在 `config/bundles.php` 中添加：

```php
return [
    Tourze\JsonRPCAsyncBundle\JsonRPCAsyncBundle::class => ['all' => true],
];
```

### 数据库设置

运行数据库迁移以创建所需的表：

```bash
php bin/console doctrine:migrations:migrate
```

### 配置 Symfony Messenger

确保在 `config/packages/messenger.yaml` 中配置了异步传输：

```yaml
framework:
    messenger:
        transports:
            async: '%env(MESSENGER_TRANSPORT_DSN)%'
        routing:
            'Tourze\JsonRPCAsyncBundle\Message\AsyncProcedureMessage': async
```

## 快速开始

1. **为 JSON-RPC 方法类加上 `AsyncExecute` 注解**。
2. **客户端发起普通 JSON-RPC 请求**（生产环境且有有效 id 时，会自动转为异步执行）。
3. **收到错误码 -799 响应**，其中包含 `taskId`，代表异步任务已启动。
4. **通过 `GetAsyncRequestResult` 方法和返回的 `taskId` 查询任务结果**。

### 示例

### 1. 标记异步方法

```php
use Tourze\JsonRPCAsyncBundle\Attribute\AsyncExecute;

#[AsyncExecute]
class MyAsyncProcedure extends BaseProcedure { ... }
```

### 2. 客户端请求

```json
{
  "jsonrpc": "2.0",
  "method": "myAsyncMethod",
  "params": { ... },
  "id": "async_123456"
}
```

### 3. 返回异步响应

```json
{
  "jsonrpc": "2.0",
  "id": "async_123456",
  "error": {
    "code": -799,
    "message": "异步执行中",
    "data": { "taskId": "..." }
  }
}
```

### 4. 查询结果

```json
{
  "jsonrpc": "2.0",
  "method": "GetAsyncRequestResult",
  "params": { "taskId": "..." },
  "id": "query_1"
}
```

## 高级用法

### 自定义配置

您可以自定义异步结果的持久化时长：

```yaml
# config/packages/framework.yaml
parameters:
    env(ASYNC_RESULT_PERSIST_DAY_NUM): 7  # 保留结果 7 天
```

### 自定义错误处理

在客户端应用中处理异步错误：

```php
if ($response['error']['code'] === -799) {
    $taskId = $response['error']['data']['taskId'];
    // 存储 taskId 以备后续查询
}
```

### 缓存配置

优化缓存设置以提升性能：

```yaml
# config/packages/cache.yaml
framework:
    cache:
        pools:
            cache.app:
                adapter: cache.adapter.redis
                default_lifetime: 3600
```

## 详细文档

### 异步流程

1. **方法拦截**：带有 `AsyncExecute` 注解的方法会被 `AsyncExecuteSubscriber` 拦截
2. **任务分发**：使用雪花ID生成器生成唯一任务ID
3. **异步处理**：任务被分发到 Symfony Messenger 进行异步处理
4. **结果存储**：结果使用 `AsyncResult` 实体持久化到数据库
5. **结果缓存**：结果被缓存以提高查询速度
6. **查询结果**：使用 `GetAsyncRequestResult` 方法查询任务结果

### 实体说明

- **AsyncResult**：存储 taskId、结果内容和创建时间
  - 使用雪花ID进行唯一标识
  - 包含自动清理的计划任务
  - 支持可配置的缓存TTL

### 配置说明

- **环境变量：**
  - `ASYNC_RESULT_PERSIST_DAY_NUM`：异步结果保留天数（默认：1天）
  - `MESSENGER_TRANSPORT_DSN`：Symfony Messenger 传输配置

### 错误码说明

- `-799`：任务已成功启动，检查 error.data 中的 `taskId`
- `-789`：任务尚未完成，请稍后重试

### 性能考虑

- 结果会被缓存以减少数据库查询
- 自动清理防止数据库膨胀
- 使用 Symfony Messenger 确保可靠的异步处理

## 贡献指南

我们欢迎贡献！请遵循以下指南：

1. **问题反馈**：通过 GitHub Issues 报告错误或请求功能
2. **拉取请求**：Fork 仓库并创建拉取请求
3. **代码风格**：遵循 PSR-12 编码标准
4. **测试**：确保所有测试通过 `./vendor/bin/phpunit`
5. **静态分析**：运行 `./vendor/bin/phpstan analyse` 检查问题

### 运行测试

```bash
# 运行所有测试
./vendor/bin/phpunit packages/json-rpc-async-bundle/tests

# 运行静态分析
php -d memory_limit=2G ./vendor/bin/phpstan analyse packages/json-rpc-async-bundle/
```

## 许可证

MIT 开源协议，详见 [LICENSE](LICENSE)。

## 更新日志

详见 [CHANGELOG](CHANGELOG.md) 了解版本历史和重大变更。
