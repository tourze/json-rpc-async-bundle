# JsonRPC 异步扩展包

[English](README.md) | [中文](README.zh-CN.md)

[![最新版本](https://img.shields.io/packagist/v/tourze/json-rpc-async-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/json-rpc-async-bundle)
[![构建状态](https://img.shields.io/travis/tourze/json-rpc-async-bundle/master.svg?style=flat-square)](https://travis-ci.org/tourze/json-rpc-async-bundle)
[![质量评分](https://img.shields.io/scrutinizer/g/tourze/json-rpc-async-bundle.svg?style=flat-square)](https://scrutinizer-ci.com/g/tourze/json-rpc-async-bundle)
[![总下载量](https://img.shields.io/packagist/dt/tourze/json-rpc-async-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/json-rpc-async-bundle)

一个用于处理 JSON-RPC 异步请求与结果的 Symfony 扩展包，支持将耗时/重型的 JSON-RPC 调用异步执行，并可通过任务ID查询执行结果。

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
- Doctrine ORM >= 2.20

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

## 快速开始

1. **为 JSON-RPC 方法类加上 `AsyncExecute` 注解**。
2. **客户端发起普通 JSON-RPC 请求**（生产环境且有有效 id 时，会自动转为异步执行）。
3. **收到错误码 -799 响应**，其中包含 `taskId`，代表异步任务已启动。
4. **通过 `GetAsyncRequestResult` 方法和返回的 `taskId` 查询任务结果**。

### 示例

#### 1. 标记异步方法

```php
use Tourze\JsonRPCAsyncBundle\Attribute\AsyncExecute;

#[AsyncExecute]
class MyAsyncProcedure extends BaseProcedure { ... }
```

#### 2. 客户端请求

```json
{
  "jsonrpc": "2.0",
  "method": "myAsyncMethod",
  "params": { ... },
  "id": "async_123456"
}
```

#### 3. 返回异步响应

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

#### 4. 查询结果

```json
{
  "jsonrpc": "2.0",
  "method": "GetAsyncRequestResult",
  "params": { "taskId": "..." },
  "id": "query_1"
}
```

## 详细文档

- **异步流程：**
  - 带有 `AsyncExecute` 注解的方法会被拦截并分发为异步任务。
  - 结果会持久化到数据库并写入缓存。
  - 通过 `GetAsyncRequestResult` 方法查询。
- **实体说明：**
  - `AsyncResult`：保存 taskId、结果内容、创建时间。
- **配置说明：**
  - 默认无需特殊配置。
- **错误码说明：**
  - `-799`：任务已启动，`taskId` 在 error.data 中。
  - `-789`：任务未完成。

## 贡献指南

欢迎提交 Issue 或 PR。请遵循 PSR 代码风格，并确保测试通过。

## 许可证

MIT 开源协议，详见 [LICENSE](LICENSE)。

## 更新日志

详见 [CHANGELOG](CHANGELOG.md)。
