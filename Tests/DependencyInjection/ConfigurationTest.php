<?php

declare(strict_types=1);

namespace Pumukit\ExternalAPIBundle\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Pumukit\ExternalAPIBundle\Controller\IngestController;
use Pumukit\ExternalAPIBundle\DependencyInjection\Configuration;
use Pumukit\ExternalAPIBundle\DependencyInjection\PumukitExternalAPIExtension;
use Pumukit\ExternalAPIBundle\Routing\RouteLoader;
use Pumukit\ExternalAPIBundle\Services\APIService;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 *
 * @coversNothing
 */
class ConfigurationTest extends TestCase
{
    public function testItNeedsNoOption(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), []);

        $this->assertTrue($config['enable']);
        $this->assertSame('CUSTOM_TAG', $config['allowed_removed_tag']);
    }

    public function testNoRouteIsLoadedWhenItIsDisabled(): void
    {
        $this->assertCount(0, (new RouteLoader(false))->load('@PumukitExternalAPIBundle/Controller/', RouteLoader::TYPE));
    }

    public function testTheDisabledBundleOnlyRegistersTheRouteLoader(): void
    {
        $container = $this->load(['enable' => false]);

        $this->assertSame([RouteLoader::class], $this->bundleServices($container));
        $this->assertFalse($container->getDefinition(RouteLoader::class)->getArgument(0));
        $this->assertSame([], $container->getParameter('pumukitschema.external_permissions'));
    }

    public function testTheEnabledBundleRegistersItsServicesAndItsPermission(): void
    {
        $container = $this->load([]);

        $this->assertTrue($container->getDefinition(RouteLoader::class)->getArgument(0));
        $this->assertTrue($container->hasDefinition(IngestController::class));
        $this->assertTrue($container->hasDefinition(APIService::class));
        $this->assertSame(['ROLE_ACCESS_INGEST_API'], array_column($container->getParameter('pumukitschema.external_permissions'), 'role'));
    }

    private function load(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('pumukitschema.external_permissions', []);
        (new PumukitExternalAPIExtension())->load([$config], $container);

        return $container;
    }

    private function bundleServices(ContainerBuilder $container): array
    {
        return array_values(array_filter(array_keys($container->getDefinitions()), static fn (string $id): bool => str_starts_with($id, 'Pumukit\ExternalAPIBundle\\')));
    }
}
