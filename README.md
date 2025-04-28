# JsonRPC Async Bundle

[English](README.md) | [中文](README.zh-CN.md)

[![Latest Version](https://img.shields.io/packagist/v/tourze/json-rpc-async-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/json-rpc-async-bundle)
[![Build Status](https://img.shields.io/travis/tourze/json-rpc-async-bundle/master.svg?style=flat-square)](https://travis-ci.org/tourze/json-rpc-async-bundle)
[![Quality Score](https://img.shields.io/scrutinizer/g/tourze/json-rpc-async-bundle.svg?style=flat-square)](https://scrutinizer-ci.com/g/tourze/json-rpc-async-bundle)
[![Total Downloads](https://img.shields.io/packagist/dt/tourze/json-rpc-async-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/json-rpc-async-bundle)

A Symfony bundle for handling asynchronous JSON-RPC requests and results, allowing heavy or long-running JSON-RPC calls to be processed asynchronously and their results to be queried later.

## Features

- Asynchronous execution for JSON-RPC methods
- Task ID based result query
- Result persistence with Doctrine ORM
- Cache-first result retrieval
- Integration with Symfony Messenger and Snowflake ID
- Automatic cleanup for expired results

## Installation

### Requirements

- PHP >= 8.1
- Symfony >= 6.4
- Doctrine ORM >= 2.20

### Install via Composer

```bash
composer require tourze/json-rpc-async-bundle
```

### Enable the Bundle

Register the bundle in your `config/bundles.php` if not auto-registered:

```php
return [
    Tourze\JsonRPCAsyncBundle\JsonRPCAsyncBundle::class => ['all' => true],
];
```

## Quick Start

1. **Mark a JSON-RPC method as async** using the `AsyncExecute` attribute.
2. **Call the method** from client with a normal JSON-RPC request. If the environment is production and the request has a valid ID, the method is executed asynchronously.
3. **Receive a taskId** in the error response (`code: -799`), indicating the async task has started.
4. **Query the result** by calling the `GetAsyncRequestResult` procedure with the returned `taskId`.

### Example

#### 1. Marking a method async

```php
use Tourze\JsonRPCAsyncBundle\Attribute\AsyncExecute;

#[AsyncExecute]
class MyAsyncProcedure extends BaseProcedure { ... }
```

#### 2. Client requests async method

```json
{
  "jsonrpc": "2.0",
  "method": "myAsyncMethod",
  "params": { ... },
  "id": "async_123456"
}
```

#### 3. Receive async response

```json
{
  "jsonrpc": "2.0",
  "id": "async_123456",
  "error": {
    "code": -799,
    "message": "Async executing",
    "data": { "taskId": "..." }
  }
}
```

#### 4. Query result

```json
{
  "jsonrpc": "2.0",
  "method": "GetAsyncRequestResult",
  "params": { "taskId": "..." },
  "id": "query_1"
}
```

## Documentation

- **Async Flow:**
  - Methods with `AsyncExecute` attribute are intercepted and dispatched as async tasks.
  - Results are persisted in DB and cached.
  - Query via `GetAsyncRequestResult` procedure.
- **Entities:**
  - `AsyncResult`: Stores taskId, result, and creation time.
- **Configuration:**
  - No special configuration required by default.
- **Error Codes:**
  - `-799`: Task started, check `taskId` in error data.
  - `-789`: Task not finished.

## Contribution

Feel free to submit issues or pull requests. Please follow PSR code style and ensure tests pass.

## License

MIT License. See [LICENSE](LICENSE).

## Changelog

See [CHANGELOG](CHANGELOG.md) for details.
