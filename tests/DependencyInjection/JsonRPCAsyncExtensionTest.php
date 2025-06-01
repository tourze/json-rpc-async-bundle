<?php

namespace Tourze\JsonRPCAsyncBundle\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Tourze\JsonRPCAsyncBundle\DependencyInjection\JsonRPCAsyncExtension;

class JsonRPCAsyncExtensionTest extends TestCase
{
    private JsonRPCAsyncExtension $extension;
    private ContainerBuilder $container;

    protected function setUp(): void
    {
        $this->extension = new JsonRPCAsyncExtension();
        $this->container = new ContainerBuilder();
    }

    public function testExtensionCanBeConstructed(): void
    {
        $this->assertInstanceOf(JsonRPCAsyncExtension::class, $this->extension);
    }

    public function testLoad_withEmptyConfig_loadsServices(): void
    {
        $this->extension->load([], $this->container);

        // 验证服务配置已加载
        $this->assertTrue($this->container->hasDefinition('Tourze\JsonRPCAsyncBundle\EventSubscriber\AsyncExecuteSubscriber'));
        $this->assertTrue($this->container->hasDefinition('Tourze\JsonRPCAsyncBundle\MessageHandler\AsyncProcedureHandler'));
        $this->assertTrue($this->container->hasDefinition('Tourze\JsonRPCAsyncBundle\Procedure\GetAsyncRequestResult'));
        $this->assertTrue($this->container->hasDefinition('Tourze\JsonRPCAsyncBundle\Repository\AsyncResultRepository'));
    }

    public function testLoad_withConfiguration_loadsServicesCorrectly(): void
    {
        $config = [
            'json_rpc_async' => [
                'some_option' => 'value'
            ]
        ];

        $this->extension->load($config, $this->container);

        // 验证服务仍然正常加载
        $this->assertTrue($this->container->hasDefinition('Tourze\JsonRPCAsyncBundle\EventSubscriber\AsyncExecuteSubscriber'));
        $this->assertTrue($this->container->hasDefinition('Tourze\JsonRPCAsyncBundle\MessageHandler\AsyncProcedureHandler'));
    }

    public function testLoad_servicesAreAutowired(): void
    {
        $this->extension->load([], $this->container);

        $subscriberDef = $this->container->getDefinition('Tourze\JsonRPCAsyncBundle\EventSubscriber\AsyncExecuteSubscriber');
        $this->assertTrue($subscriberDef->isAutowired());

        $handlerDef = $this->container->getDefinition('Tourze\JsonRPCAsyncBundle\MessageHandler\AsyncProcedureHandler');
        $this->assertTrue($handlerDef->isAutowired());

        $procedureDef = $this->container->getDefinition('Tourze\JsonRPCAsyncBundle\Procedure\GetAsyncRequestResult');
        $this->assertTrue($procedureDef->isAutowired());

        $repositoryDef = $this->container->getDefinition('Tourze\JsonRPCAsyncBundle\Repository\AsyncResultRepository');
        $this->assertTrue($repositoryDef->isAutowired());
    }

    public function testLoad_servicesAreAutoconfigured(): void
    {
        $this->extension->load([], $this->container);

        $subscriberDef = $this->container->getDefinition('Tourze\JsonRPCAsyncBundle\EventSubscriber\AsyncExecuteSubscriber');
        $this->assertTrue($subscriberDef->isAutoconfigured());

        $handlerDef = $this->container->getDefinition('Tourze\JsonRPCAsyncBundle\MessageHandler\AsyncProcedureHandler');
        $this->assertTrue($handlerDef->isAutoconfigured());

        $procedureDef = $this->container->getDefinition('Tourze\JsonRPCAsyncBundle\Procedure\GetAsyncRequestResult');
        $this->assertTrue($procedureDef->isAutoconfigured());

        $repositoryDef = $this->container->getDefinition('Tourze\JsonRPCAsyncBundle\Repository\AsyncResultRepository');
        $this->assertTrue($repositoryDef->isAutoconfigured());
    }

    public function testLoad_multipleCallsDoNotDuplicate(): void
    {
        $this->extension->load([], $this->container);
        $firstCallServiceCount = count($this->container->getDefinitions());

        $this->extension->load([], $this->container);
        $secondCallServiceCount = count($this->container->getDefinitions());

        // 第二次调用不应该增加服务数量（不应该重复加载）
        $this->assertEquals($firstCallServiceCount, $secondCallServiceCount);
    }
} 