<?php

namespace SkyDiablo\AWSBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;

/**
 * This is the class that loads and manages your bundle configuration.
 *
 * @link http://symfony.com/doc/current/cookbook/bundles/extension.html
 */
class SkyDiabloAWSExtension extends Extension
{

    const ALIAS = 'skydiablo_aws';
    const SERVICE_ID_COMMAND_ELASTIC_BEANSTALK_CLIENT = 'skydiablo.aws.command.elastic_beanstalk.client';

    /**
     * @param array $configs
     * @param ContainerBuilder $container
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $ebClient = new Alias($config['command']['elastic_beanstalk']['aws_client']);
        $container->setAlias(self::SERVICE_ID_COMMAND_ELASTIC_BEANSTALK_CLIENT, $ebClient);


        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yml');
    }

    public function getAlias()
    {
        return self::ALIAS;
    }


}
