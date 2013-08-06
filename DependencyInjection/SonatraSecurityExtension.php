<?php

/*
 * This file is part of the Sonatra package.
 *
 * (c) François Pluchino <francois.pluchino@sonatra.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sonatra\Bundle\SecurityBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * The extension that fulfills the infos for the container from configuration.
 *
 * @author François Pluchino <francois.pluchino@sonatra.com>
 */
class SonatraSecurityExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);
        $loader = new Loader\YamlFileLoader($container,new FileLocator(__DIR__ . '/../Resources/config'));

        // entity classes
        $container->setParameter('sonatra_security.user_class', $config['user_class']);
        $container->setParameter('sonatra_security.role_class', $config['role_class']);
        $container->setParameter('sonatra_security.group_class', $config['group_class']);

        // host role
        $loader->load('host_role.yml');

        // acl
        if ($config['acl']['enabled']) {
            if ($config['acl']['enabled_hierarchy']) {
                $loader->load('acl_hierarchy.yml');
            }

            if ($config['acl']['enabled_group_security_identity']) {
                $loader->load('group_security_identity_strategy.yml');
            }

            $container->setParameter('sonatra_security.acl_default_rule', $config['acl']['default_rule']);
            $container->setParameter('sonatra_security.acl_rules', $config['acl']['rules']);

            $loader->load('acl.yml');

            // listener
            if ($config['acl']['doctrine_orm_listener']) {
                $loader->load('acl_doctrine_orm_listener.yml');
            }
        }

        // expression
        if ($config['expression']['replace_has_permission']) {
            $loader->load('expression_has_permission.yml');
        }

        if ($config['expression']['add_has_field_permission']) {
            $loader->load('expression_has_field_permission.yml');
        }

        if ($config['doctrine_listener']['enabled']) {
            $loader->load('doctrine_listener_role.yml');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function configValidate(ContainerBuilder $container)
    {
        foreach (array_keys($this->entityManagers) as $name) {
            if (!$container->hasDefinition(sprintf('doctrine.dbal.%s_connection', $name))) {
                throw new \InvalidArgumentException(sprintf('Invalid %s config: DBAL connection "%s" not found', $this->getAlias(), $name));
            }
        }

        foreach (array_keys($this->documentManagers) as $name) {
            if (!$container->hasDefinition(sprintf('doctrine.odm.mongodb.%s_document_manager', $name))) {
                throw new \InvalidArgumentException(sprintf('Invalid %s config: document manager "%s" not found', $this->getAlias(), $name));
            }
        }
    }

    /**
     * This function analyses the classname to change / in \\ if there are some in the given classname.
     *
     * @param string $className The class name to be converted with antislashes
     */
    private function getNormalizedClassName($className)
    {
        $className = str_replace('/', '\\', $className);

        return $className;
    }
}
