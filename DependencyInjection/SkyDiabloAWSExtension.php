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
    const SERVICE_ID_COMMAND_CLOUD_SEARCH_CLIENT_SEARCH = 'skydiablo.aws.command.cloud_search.client.search';
    const SERVICE_ID_COMMAND_CLOUD_SEARCH_CLIENT_DOC = 'skydiablo.aws.command.cloud_search.client.doc';

    /**
     * @param array $configs
     * @param ContainerBuilder $container
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $awsClient = new Alias($config['command']['elastic_beanstalk']['aws_client']);
        $container->setAlias(self::SERVICE_ID_COMMAND_ELASTIC_BEANSTALK_CLIENT, $awsClient);

        foreach ($config['command']['cloud_search'] AS $key => $client) {
            $awsClient = new Alias($client['aws_search_client']);
            $container->setAlias(sprintf('%s.%s', self::SERVICE_ID_COMMAND_CLOUD_SEARCH_CLIENT_SEARCH, $key), $awsClient);

            $awsClient = new Alias($client['aws_doc_client']);
            $container->setAlias(sprintf('', self::SERVICE_ID_COMMAND_CLOUD_SEARCH_CLIENT_DOC, $key), $awsClient);
        }


        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yml');
    }

    public function getAlias()
    {
        return self::ALIAS;
    }


}
