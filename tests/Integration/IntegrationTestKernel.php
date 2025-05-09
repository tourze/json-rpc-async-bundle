<?php

namespace Tourze\JsonRPCAsyncBundle\Tests\Integration;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Tourze\JsonRPCAsyncBundle\JsonRPCAsyncBundle;
use Tourze\SnowflakeBundle\Service\Snowflake;

class IntegrationTestKernel extends Kernel
{
    use MicroKernelTrait;

    public function __construct()
    {
        parent::__construct('test', true);
    }

    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
            new DoctrineBundle(),
            new JsonRPCAsyncBundle(),
        ];
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'test' => true,
            'secret' => 'test',
            'handle_all_throwables' => true,
            'http_method_override' => false,
            'php_errors' => [
                'log' => true,
            ],
            'cache' => [
                'app' => 'cache.adapter.array',
            ],
            'messenger' => [
                'default_bus' => 'messenger.bus.default',
                'buses' => [
                    'messenger.bus.default' => [],
                ],
                'transports' => [
                    'sync' => 'sync://',
                ],
                'routing' => [
                    'Tourze\JsonRPCAsyncBundle\Message\AsyncProcedureMessage' => 'sync',
                ],
            ],
        ]);

        // 配置Doctrine
        $container->extension('doctrine', [
            'dbal' => [
                'default_connection' => 'default',
                'connections' => [
                    'default' => [
                        'driver' => 'pdo_sqlite',
                        'path' => '%kernel.cache_dir%/test.db',
                    ],
                ],
            ],
            'orm' => [
                'auto_generate_proxy_classes' => true,
                'enable_lazy_ghost_objects' => true,
                'default_entity_manager' => 'default',
                'entity_managers' => [
                    'default' => [
                        'connection' => 'default',
                        'naming_strategy' => 'doctrine.orm.naming_strategy.underscore_number_aware',
                        'auto_mapping' => true,
                        'mappings' => [
                            'JsonRPCAsyncBundle' => [
                                'type' => 'attribute',
                                'dir' => '%kernel.project_dir%/packages/json-rpc-async-bundle/src/Entity',
                                'prefix' => 'Tourze\JsonRPCAsyncBundle\Entity',
                                'is_bundle' => false,
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        // 注册测试服务
        $services = $container->services()
            ->defaults()
            ->autowire()
            ->autoconfigure();

        // 手动注册 Snowflake 服务
        $services->set(Snowflake::class)
            ->public()
            ->autowire();
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/json_rpc_async_bundle_cache';
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/json_rpc_async_bundle_logs';
    }
}
