<?php

/*
 * This file is part of the jolicode/elastically library.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use JoliCode\Elastically\Client;
use JoliCode\Elastically\IndexBuilder;
use JoliCode\Elastically\Indexer;
use JoliCode\Elastically\IndexNameMapper;
use JoliCode\Elastically\Mapping\YamlProvider;
use JoliCode\Elastically\ResultSetBuilder;
use JoliCode\Elastically\Serializer\DocumentSerializer;
use JoliCode\Elastically\Serializer\JsonStreamerAdapter;
use JoliCode\Elastically\Serializer\StaticContextBuilder;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('elastically.abstract.index_name_mapper', IndexNameMapper::class)
            ->abstract()
            ->args([
                '$prefix' => abstract_arg('prefix'),
                '$indexClassMapping' => abstract_arg('index class mapping'),
            ])

        ->set('elastically.abstract.document_serializer', DocumentSerializer::class)
            ->abstract()
            ->args([
                '$serializer' => service('serializer'),
                '$contextBuilder' => abstract_arg('elastically.abstract.static_context_builder'),
            ])

        ->set('elastically.abstract.document_streamer', JsonStreamerAdapter::class)
            ->abstract()
            ->args([
                '$decorated' => abstract_arg('elastically.abstract.document_serializer'),
                '$streamWriter' => service('json_streamer.stream_writer')->ignoreOnInvalid(),
                '$cache' => service('cache.app'),
            ])

        ->set('elastically.abstract.static_context_builder', StaticContextBuilder::class)
            ->abstract()
            ->args([
                '$mapping' => abstract_arg('mapping'),
            ])

        ->set('elastically.abstract.result_set_builder', ResultSetBuilder::class)
            ->abstract()
            ->args([
                '$indexNameMapper' => abstract_arg('elastically.abstract.index_name_mapper'),
                '$contextBuilder' => abstract_arg('elastically.abstract.static_context_builder'),
                '$denormalizer' => service('serializer'),
            ])

        ->set('elastically.abstract.client', Client::class)
            ->abstract()
            ->args([
                '$config' => abstract_arg('config'),
                '$logger' => service('logger')->nullOnInvalid(),
                '$resultSetBuilder' => abstract_arg('elastically.abstract.result_set_builder'),
                '$indexNameMapper' => abstract_arg('elastically.abstract.index_name_mapper'),
            ])
            ->tag('monolog.logger', ['channel' => 'elastically'])

        ->set('elastically.abstract.indexer', Indexer::class)
            ->abstract()
            ->args([
                '$client' => service(Client::class),
                '$serializer' => abstract_arg('elastically.abstract.document_serializer'),
                '$bulkMaxSize' => abstract_arg('bulk size'),
                '$bulkRequestParams' => [],
            ])

        ->set('elastically.abstract.mapping.provider', YamlProvider::class)
            ->abstract()
            ->args([
                '$configurationDirectory' => abstract_arg('configurationDirectory'),
            ])

        ->set('elastically.abstract.index_builder', IndexBuilder::class)
            ->abstract()
            ->args([
                '$mappingProvider' => abstract_arg('elastically.mapping.provider'),
                '$client' => abstract_arg('elastically.abstract.client'),
                '$indexNameMapper' => abstract_arg('elastically.abstract.index_name_mapper'),
            ])
    ;
};
