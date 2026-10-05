<?php

/*
 * This file is part of the jolicode/elastically library.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JoliCode\Elastically\Bridge\Symfony\DependencyInjection\Compiler;

use JoliCode\Elastically\Bridge\Symfony\DataCollector\ElasticallyDataCollector;
use JoliCode\Elastically\Bridge\Symfony\DataCollector\TraceableClient;
use JoliCode\Elastically\Bridge\Symfony\DataCollector\TraceableResultSetBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Traces the requests and the hydrated models of every connection when the Symfony profiler is enabled.
 */
class DataCollectorPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        // Always read the tag, so it is not reported as unused
        $clients = $container->findTaggedServiceIds('elastically.client');

        if (!$container->hasDefinition('profiler')) {
            return;
        }

        $collector = $container->register('elastically.data_collector', ElasticallyDataCollector::class)
            ->addTag('data_collector', [
                'template' => '@Elastically/Collector/elastically.html.twig',
                'id' => 'elastically',
                'priority' => 250,
            ])
        ;

        foreach ($clients as $id => $tags) {
            $connection = $tags[0]['connection'];

            $container->getDefinition($id)
                ->setClass(TraceableClient::class)
                ->addMethodCall('setStopwatch', [new Reference('debug.stopwatch', ContainerInterface::NULL_ON_INVALID_REFERENCE)])
            ;

            $container->getDefinition($resultSetBuilderId = "elastically.{$connection}.result_set_builder")
                ->setClass(TraceableResultSetBuilder::class)
                ->addMethodCall('setClient', [new Reference($id)])
            ;

            $collector->addMethodCall('addClient', [
                $connection,
                new Reference($id),
                new Reference($resultSetBuilderId),
                new Reference("elastically.{$connection}.index_name_mapper"),
            ]);
        }
    }
}
