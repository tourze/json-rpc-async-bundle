# JsonRPC Async Bundle

[English](README.md) | [中文](README.zh-CN.md)

[![PHP Version](https://img.shields.io/packagist/dependency-v/tourze/json-rpc-async-bundle/php?style=flat-square)](https://packagist.org/packages/tourze/json-rpc-async-bundle)
[![License](https://img.shields.io/packagist/l/tourze/json-rpc-async-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/json-rpc-async-bundle)
[![Latest Version](https://img.shields.io/packagist/v/tourze/json-rpc-async-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/json-rpc-async-bundle)
[![Build Status](https://img.shields.io/travis/tourze/json-rpc-async-bundle/master.svg?style=flat-square)](https://travis-ci.org/tourze/json-rpc-async-bundle)
[![Quality Score](https://img.shields.io/scrutinizer/g/tourze/json-rpc-async-bundle.svg?style=flat-square)](https://scrutinizer-ci.com/g/tourze/json-rpc-async-bundle)
[![Code Coverage](https://img.shields.io/scrutinizer/coverage/g/tourze/json-rpc-async-bundle.svg?style=flat-square)](https://scrutinizer-ci.com/g/tourze/json-rpc-async-bundle)
[![Total Downloads](https://img.shields.io/packagist/dt/tourze/json-rpc-async-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/json-rpc-async-bundle)

A Symfony bundle for handling asynchronous JSON-RPC requests and results, 
allowing heavy or long-running JSON-RPC calls to be processed asynchronously 
and their results to be queried later.

## Table of Contents

- [Features](#features)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Advanced Usage](#advanced-usage)
- [Documentation](#documentation)
- [Contributing](#contributing)
- [License](#license)
- [Changelog](#changelog)

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
- Doctrine ORM >= 3.0
- Symfony Messenger for async task processing

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

### Database Setup

Run the database migrations to create the required tables:

```bash
php bin/console doctrine:migrations:migrate
```

### Configure Symfony Messenger

Ensure your `config/packages/messenger.yaml` has async transport configured:

```yaml
framework:
    messenger:
        transports:
            async: '%env(MESSENGER_TRANSPORT_DSN)%'
        routing:
            'Tourze\JsonRPCAsyncBundle\Message\AsyncProcedureMessage': async
```

## Quick Start

1. **Mark a JSON-RPC method as async** using the `AsyncExecute` attribute.
2. **Call the method** from client with a normal JSON-RPC request. If the environment is production and the request has a valid ID, the method is executed asynchronously.
3. **Receive a taskId** in the error response (`code: -799`), indicating the async task has started.
4. **Query the result** by calling the `GetAsyncRequestResult` procedure with the returned `taskId`.

### Example

### 1. Marking a method async

```php
use Tourze\JsonRPCAsyncBundle\Attribute\AsyncExecute;

#[AsyncExecute]
class MyAsyncProcedure extends BaseProcedure { ... }
```

### 2. Client requests async method

```json
{
  "jsonrpc": "2.0",
  "method": "myAsyncMethod",
  "params": { ... },
  "id": "async_123456"
}
```

### 3. Receive async response

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

### 4. Query result

```json
{
  "jsonrpc": "2.0",
  "method": "GetAsyncRequestResult",
  "params": { "taskId": "..." },
  "id": "query_1"
}
```

## Advanced Usage

### Custom Configuration

You can customize the async result persistence duration:

```yaml
# config/packages/framework.yaml
parameters:
    env(ASYNC_RESULT_PERSIST_DAY_NUM): 7  # Keep results for 7 days
```

### Custom Error Handling

Handle async errors in your client application:

```php
if ($response['error']['code'] === -799) {
    $taskId = $response['error']['data']['taskId'];
    // Store taskId for later query
}
```

### Cache Configuration

Optimize cache settings for better performance:

```yaml
# config/packages/cache.yaml
framework:
    cache:
        pools:
            cache.app:
                adapter: cache.adapter.redis
                default_lifetime: 3600
```

## Documentation

### Async Flow

1. **Method Interception**: Methods with `AsyncExecute` attribute are intercepted by `AsyncExecuteSubscriber`
2. **Task Dispatch**: A unique task ID is generated using Snowflake ID generator
3. **Async Processing**: The task is dispatched to Symfony Messenger for async processing
4. **Result Storage**: Results are persisted in database using `AsyncResult` entity
5. **Result Caching**: Results are cached for faster retrieval
6. **Query Results**: Use `GetAsyncRequestResult` procedure to query task results

### Entities

- **AsyncResult**: Stores taskId, result content, and creation time
  - Uses Snowflake ID for unique identification
  - Includes automatic cleanup via schedule
  - Cached with configurable TTL

### Configuration

- **Environment Variables:**
  - `ASYNC_RESULT_PERSIST_DAY_NUM`: How many days to keep async results (default: 1)
  - `MESSENGER_TRANSPORT_DSN`: Symfony Messenger transport configuration

### Error Codes

- `-799`: Task started successfully, check `taskId` in error data
- `-789`: Task not finished yet, try again later

### Performance Considerations

- Results are cached to reduce database queries
- Automatic cleanup prevents database bloat
- Uses Symfony Messenger for reliable async processing

## Contributing

We welcome contributions! Please follow these guidelines:

1. **Issues**: Report bugs or request features via GitHub Issues
2. **Pull Requests**: Fork the repository and create a pull request
3. **Code Style**: Follow PSR-12 coding standards
4. **Tests**: Ensure all tests pass with `./vendor/bin/phpunit`
5. **Static Analysis**: Run `./vendor/bin/phpstan analyse` to check for issues

### Running Tests

```bash
# Run all tests
./vendor/bin/phpunit packages/json-rpc-async-bundle/tests

# Run static analysis
php -d memory_limit=2G ./vendor/bin/phpstan analyse packages/json-rpc-async-bundle/
```

## License

The MIT License (MIT). Please see [LICENSE](LICENSE) for more information.

## Changelog

See [CHANGELOG](CHANGELOG.md) for version history and breaking changes.
