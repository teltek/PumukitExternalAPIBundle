<?php

declare(strict_types=1);

namespace Pumukit\ExternalAPIBundle\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Pumukit\ExternalAPIBundle\DependencyInjection\Configuration;
use Pumukit\ExternalAPIBundle\Routing\RouteLoader;
use Symfony\Component\Config\Definition\Processor;

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
}
