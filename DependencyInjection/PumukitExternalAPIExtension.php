<?php

namespace Pumukit\ExternalAPIBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class PumukitExternalAPIExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('pumukit_external_api.allowed_removed_tag', $config['allowed_removed_tag']);

        $loader = new Loader\XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.xml');

        $permissions = [['role' => 'ROLE_ACCESS_INGEST_API', 'description' => 'Access the Ingest API']];
        $newPermissions = array_merge($container->getParameter('pumukitschema.external_permissions'), $permissions);
        $container->setParameter('pumukitschema.external_permissions', $newPermissions);
    }
}
