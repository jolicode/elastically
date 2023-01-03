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

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('elastically');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->arrayNode('connections')
                    ->normalizeKeys(false)
                    ->prototype('array')
                        ->children()
                            ->arrayNode('client')
                                ->info('All options for the Elastica client constructor')
                                ->example([
                                    'host' => '%env(ELASTICSEARCH_HOST)%',
                                    'transport' => '@JoliCode\Elastically\Transport\HttpClientTransport',
                                ])
                                ->prototype('variable')->end()
                            ->end()
                            ->scalarNode('mapping_directory')
                                ->info('Path to the mapping directory (in YAML)')
                                ->example('%kernel.project_dir%/config/elasticsearch')
                                ->cannotBeEmpty()
                            ->end()
                            ->scalarNode('mapping_provider_service')
                                ->info('For custom mapping provider')
                            ->end()
                            ->arrayNode('index_class_mapping')
                                ->info('Mapping between an index name and a FQCN')
                                ->example([
                                    'my-foobar-index' => 'App\Dto\Foobar',
                                ])
                                ->isRequired()
                                ->cannotBeEmpty()
                                ->normalizeKeys(false)
                                ->prototype('scalar')
                                    ->info('A FQCN')
                                    ->example('App\Dto\Foobar')
                                    ->cannotBeEmpty()
                                ->end()
                            ->end()
                            ->scalarNode('prefix')
                                ->info('A prefix for all elasticsearch indices')
                                ->defaultNull()
                            ->end()
                            ->integerNode('bulk_size')
                                ->info('When running indexation of lots of documents, this setting allow you to fine-tune the number of document threshold.')
                                ->defaultValue(100)
                            ->end()
                            ->arrayNode('serializer')
                                ->info('Configuration for the serializer')
                                ->addDefaultsIfNotSet()
                                ->children()
                                    ->arrayNode('context_mapping')
                                        ->info('Fill a static context')
                                        ->example(['foo' => 'bar'])
                                        ->normalizeKeys(false)
                                        ->defaultValue([])
                                        ->prototype('variable')->end()
                                    ->end()
                                    ->scalarNode('context_builder_service')
                                        ->info('For custom context builder')
                                        ->cannotBeEmpty()
                                    ->end()
                                ->end()
                                ->validate()
                                    ->ifTrue(fn (array $config): bool => isset($config['context_builder_service']) && $config['context_mapping'])
                                    ->thenInvalid('You cannot use "context_builder_service" and "context_mapping" at the same time.')
                                ->end()
                            ->end()
                        ->end()
                        ->validate()
                            ->ifTrue(fn (array $config): bool => isset($config['mapping_directory'], $config['mapping_provider_service']))
                            ->thenInvalid('You cannot use "mapping_directory" and "mapping_provider_service" at the same time.')
                        ->end()
                        ->validate()
                            ->ifTrue(fn (array $config): bool => !isset($config['mapping_directory']) && !isset($config['mapping_provider_service']))
                            ->thenInvalid('At least one option between "mapping_directory" and "mapping_provider_service" must be used.')
                        ->end()
                    ->end()
                ->end()
                ->scalarNode('default_connection')
                    ->info('The default connection name. Useful for autowire')
                    ->defaultNull()
                ->end()
            ->end()
            ->validate()
                ->ifTrue(fn (array $config): bool => $config['default_connection'] && !\array_key_exists($config['default_connection'], $config['connections']))
                ->then(function (array $v) {
                    throw new InvalidConfigurationException(sprintf('The default connection "%s" does not exists. Available connections are: "%s".', $v['default_connection'], implode('", "', array_keys($v['connections']))));
                })
            ->end()
        ;

        return $treeBuilder;
    }
}
