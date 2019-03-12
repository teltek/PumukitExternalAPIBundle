<?php

namespace Pumukit\ExternalAPIBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * This is the class that loads and manages your bundle configuration.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class PumukitExternalAPIExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $permissions = array(array('role' => 'ROLE_ACCESS_INGEST_API', 'description' => 'Access the Ingest API'));
        $newPermissions = array_merge($container->getParameter('pumukitschema.external_permissions'), $permissions);
        $container->setParameter('pumukitschema.external_permissions', $newPermissions);
    }
}
