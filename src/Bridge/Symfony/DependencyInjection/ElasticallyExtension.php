<?php

/*
 * This file is part of the jolicode/elastically library.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JoliCode\Elastically\Bridge\Symfony\DependencyInjection;

use JoliCode\Elastically\Client;
use JoliCode\Elastically\IndexBuilder;
use JoliCode\Elastically\Indexer;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class ElasticallyExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $config = $this->processConfiguration($this->getConfiguration($configs, $container), $configs);
        $loader = new PhpFileLoader($container, new FileLocator(\dirname(__DIR__) . '/Resources/config'));

        $loader->load('services.php');

        $defaultConnectionName = null;
        if ($config['default_connection']) {
            $defaultConnectionName = $config['default_connection'];
        } elseif (1 === \count($config['connections'])) {
            $defaultConnectionName = key($config['connections']);
        }

        foreach ($config['connections'] as $name => $connectionConfig) {
            $this->buildConnection($name, $connectionConfig, $name === $defaultConnectionName, $container);
        }
    }

    private function buildConnection(string $name, array $config, bool $isDefaultConnection, ContainerBuilder $container)
    {
        $indexNameMapper = new ChildDefinition('elastically.abstract.index_name_mapper');
        $indexNameMapper->replaceArgument('$prefix', $config['prefix']);
        $indexNameMapper->replaceArgument('$indexClassMapping', $config['index_class_mapping']);
        $indexNameMapper->replaceArgument('$bulkMaxSize', $config['bulk_size']);
        $container->setDefinition("elastically.{$name}.index_name_mapper", $indexNameMapper);

        if (\array_key_exists('context_builder_service', $config['serializer'])) {
            $container->setAlias("elastically.{$name}.static_context_builder", $config['serializer']['context_builder_service']);
        } else {
            $staticContextBuilder = new ChildDefinition('elastically.abstract.static_context_builder');
            $staticContextBuilder->replaceArgument('$mapping', $config['serializer']['context_mapping']);
            $container->setDefinition("elastically.{$name}.static_context_builder", $staticContextBuilder);
        }

        $resultSetBuilder = new ChildDefinition('elastically.abstract.result_set_builder');
        $resultSetBuilder->replaceArgument('$indexNameMapper', new Reference("elastically.{$name}.index_name_mapper"));
        $resultSetBuilder->replaceArgument('$contextBuilder', new Reference("elastically.{$name}.static_context_builder"));
        $container->setDefinition("elastically.{$name}.result_set_builder", $resultSetBuilder);

        $client = new ChildDefinition('elastically.abstract.client');
        $client->replaceArgument('$config', $config['client']);
        $client->replaceArgument('$resultSetBuilder', new Reference("elastically.{$name}.result_set_builder"));
        $client->replaceArgument('$indexNameMapper', new Reference("elastically.{$name}.index_name_mapper"));
        $container->setDefinition($id = "elastically.{$name}.client", $client);
        if ($isDefaultConnection) {
            $container->setAlias(Client::class, $id);
        }
        $container->registerAliasForArgument($id, Client::class, $name . 'Client');

        $indexer = new ChildDefinition('elastically.abstract.indexer');
        $indexer->replaceArgument('$client', new Reference("elastically.{$name}.client"));
        $indexer->replaceArgument('$contextBuilder', new Reference("elastically.{$name}.static_context_builder"));
        $container->setDefinition($id = "elastically.{$name}.indexer", $indexer);
        if ($isDefaultConnection) {
            $container->setAlias(Indexer::class, $id);
        }
        $container->registerAliasForArgument($id, Indexer::class, $name . 'Indexer');

        if (\array_key_exists('mapping_provider_service', $config)) {
            $container->setAlias("elastically.{$name}.mapping.provider", $config['mapping_provider_service']);
        } else {
            $mappingProvider = new ChildDefinition('elastically.abstract.mapping.provider');
            $mappingProvider->replaceArgument('$configurationDirectory', $config['mapping_directory']);
            $container->setDefinition("elastically.{$name}.mapping.provider", $mappingProvider);
        }

        $indexBuilder = new ChildDefinition('elastically.abstract.index_builder');
        $indexBuilder->replaceArgument('$mappingProvider', new Reference("elastically.{$name}.mapping.provider"));
        $indexBuilder->replaceArgument('$client', new Reference("elastically.{$name}.client"));
        $indexBuilder->replaceArgument('$indexNameMapper', new Reference("elastically.{$name}.index_name_mapper"));
        $container->setDefinition($id = "elastically.{$name}.index_builder", $indexBuilder);
        if ($isDefaultConnection) {
            $container->setAlias(IndexBuilder::class, $id);
        }
        $container->registerAliasForArgument($id, IndexBuilder::class, $name . 'IndexBuilder');
    }
}
