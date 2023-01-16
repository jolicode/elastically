<?php

/*
 * This file is part of the jolicode/elastically library.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JoliCode\Elastically\Tests\Bridge\Symfony\DependencyInjection;

use JoliCode\Elastically\Bridge\Symfony\DependencyInjection\ElasticallyExtension;
use JoliCode\Elastically\Bridge\Symfony\ElasticallyBundle;
use JoliCode\Elastically\Client;
use JoliCode\Elastically\IndexBuilder;
use JoliCode\Elastically\Indexer;
use JoliCode\Elastically\Transport\HttpClientTransport;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class ElasticallyExtensionTest extends TestCase
{
    public function testWithoutConnections(): void
    {
        $container = $this->buildContainer();

        $container->loadFromExtension('elastically', [
        ]);

        $container->compile();

        $this->assertFalse($container->hasDefinition('elastically.foobar.client'));
        $this->assertFalse($container->hasAlias(Client::class));
        $this->assertFalse($container->hasDefinition('elastically.foobar.index_builder'));
        $this->assertFalse($container->hasAlias(IndexBuilder::class));
        $this->assertFalse($container->hasDefinition('elastically.foobar.indexer'));
        $this->assertFalse($container->hasAlias(Indexer::class));
    }

    public function testWithOneConnection(): void
    {
        $container = $this->buildContainer();

        $container->loadFromExtension('elastically', [
            'connections' => [
                'foobar' => [
                    'mapping_directory' => __DIR__,
                    'index_class_mapping' => ['foobar' => self::class],
                    'bulk_size' => 5000,
                ],
            ],
        ]);

        $container->compile();

        $this->assertTrue($container->hasDefinition('elastically.foobar.client'));
        $this->assertTrue($container->hasAlias(Client::class));
        $this->assertTrue($container->hasAlias(Client::class . ' $foobarClient'));
        $this->assertTrue($container->hasDefinition('elastically.foobar.index_builder'));
        $this->assertTrue($container->hasAlias(IndexBuilder::class));
        $this->assertTrue($container->hasAlias(IndexBuilder::class . ' $foobarIndexBuilder'));
        $this->assertTrue($container->hasDefinition('elastically.foobar.indexer'));
        $this->assertTrue($container->hasAlias(Indexer::class));
        $this->assertTrue($container->hasAlias(Indexer::class . ' $foobarIndexer'));
    }

    public function testWithTwoConnectionsAndNotDefault(): void
    {
        $container = $this->buildContainer();

        $container->loadFromExtension('elastically', [
            'connections' => [
                'foobar' => [
                    'mapping_directory' => __DIR__,
                    'index_class_mapping' => ['foobar' => self::class],
                ],
                'another' => [
                    'mapping_directory' => __DIR__,
                    'index_class_mapping' => ['foobar' => self::class],
                ],
            ],
        ]);

        $container->compile();

        $this->assertTrue($container->hasDefinition('elastically.foobar.client'));
        $this->assertTrue($container->hasAlias(Client::class . ' $foobarClient'));
        $this->assertTrue($container->hasDefinition('elastically.foobar.index_builder'));
        $this->assertTrue($container->hasAlias(IndexBuilder::class . ' $foobarIndexBuilder'));
        $this->assertTrue($container->hasDefinition('elastically.foobar.indexer'));
        $this->assertTrue($container->hasAlias(Indexer::class . ' $foobarIndexer'));

        $this->assertTrue($container->hasDefinition('elastically.another.client'));
        $this->assertTrue($container->hasAlias(Client::class . ' $anotherClient'));
        $this->assertTrue($container->hasDefinition('elastically.another.index_builder'));
        $this->assertTrue($container->hasAlias(IndexBuilder::class . ' $anotherIndexBuilder'));
        $this->assertTrue($container->hasDefinition('elastically.another.indexer'));
        $this->assertTrue($container->hasAlias(Indexer::class . ' $anotherIndexer'));

        $this->assertFalse($container->hasAlias(Client::class));
        $this->assertFalse($container->hasAlias(IndexBuilder::class));
        $this->assertFalse($container->hasAlias(Indexer::class));
    }

    public function testWithTwoConnectionsAndADefault(): void
    {
        $container = $this->buildContainer();

        $container->loadFromExtension('elastically', [
            'connections' => [
                'foobar' => [
                    'mapping_directory' => __DIR__,
                    'index_class_mapping' => ['foobar' => self::class],
                ],
                'another' => [
                    'mapping_directory' => __DIR__,
                    'index_class_mapping' => ['foobar' => self::class],
                ],
            ],
            'default_connection' => 'another',
        ]);

        $container->compile();

        $this->assertTrue($container->hasDefinition('elastically.foobar.client'));
        $this->assertTrue($container->hasAlias(Client::class . ' $foobarClient'));
        $this->assertTrue($container->hasDefinition('elastically.foobar.index_builder'));
        $this->assertTrue($container->hasAlias(IndexBuilder::class . ' $foobarIndexBuilder'));
        $this->assertTrue($container->hasDefinition('elastically.foobar.indexer'));
        $this->assertTrue($container->hasAlias(Indexer::class . ' $foobarIndexer'));

        $this->assertTrue($container->hasDefinition('elastically.another.client'));
        $this->assertTrue($container->hasAlias(Client::class . ' $anotherClient'));
        $this->assertTrue($container->hasDefinition('elastically.another.index_builder'));
        $this->assertTrue($container->hasAlias(IndexBuilder::class . ' $anotherIndexBuilder'));
        $this->assertTrue($container->hasDefinition('elastically.another.indexer'));
        $this->assertTrue($container->hasAlias(Indexer::class . ' $anotherIndexer'));

        $this->assertTrue($container->hasAlias(Client::class));
        $this->assertSame('elastically.another.client', (string) $container->getAlias(Client::class));
        $this->assertTrue($container->hasAlias(IndexBuilder::class));
        $this->assertSame('elastically.another.index_builder', (string) $container->getAlias(IndexBuilder::class));
        $this->assertTrue($container->hasAlias(Indexer::class));
        $this->assertSame('elastically.another.indexer', (string) $container->getAlias(Indexer::class));
    }

    public function testMissingClassMapping(): void
    {
        $container = $this->buildContainer();

        $container->loadFromExtension('elastically', [
            'connections' => [
                'foobar' => [
                ],
            ],
        ]);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The child config "index_class_mapping" under "elastically.connections.foobar" must be configured: Mapping between an index name and a FQCN');

        $container->compile();
    }

    public function testDoubleMissingMappingProvider(): void
    {
        $container = $this->buildContainer();

        $container->loadFromExtension('elastically', [
            'connections' => [
                'foobar' => [
                    'index_class_mapping' => ['foobar' => 'App\Dto\Foobar'],
                ],
            ],
        ]);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Invalid configuration for path "elastically.connections.foobar": At least one option between "mapping_directory" and "mapping_provider_service" must be used.');

        $container->compile();
    }

    public function testDoubleDoubleMappingProvider(): void
    {
        $container = $this->buildContainer();

        $container->loadFromExtension('elastically', [
            'connections' => [
                'foobar' => [
                    'index_class_mapping' => ['foobar' => 'App\Dto\Foobar'],
                    'mapping_directory' => __DIR__,
                    'mapping_provider_service' => 'foobar',
                ],
            ],
        ]);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Invalid configuration for path "elastically.connections.foobar": You cannot use "mapping_directory" and "mapping_provider_service" at the same time.');

        $container->compile();
    }

    public function testWithTransport(): void
    {
        $container = $this->buildContainer();

        $container->loadFromExtension('elastically', [
            'connections' => [
                'default' => [
                    'client' => [
                        'transport' => HttpClientTransport::class,
                    ],
                    'mapping_directory' => __DIR__,
                    'index_class_mapping' => ['foobar' => self::class],
                ],
            ],
        ]);

        $container->compile();

        $configArgument = $container->getDefinition('elastically.default.client')->getArgument('$config');
        $this->assertInstanceOf(Reference::class, $configArgument['transport']);
    }

    private function buildContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', true);

        $extension = new ElasticallyExtension();
        $container->registerExtension($extension);

        $container->getCompilerPassConfig()->setOptimizationPasses([]);
        $container->getCompilerPassConfig()->setRemovingPasses([]);
        $container->getCompilerPassConfig()->setAfterRemovingPasses([]);

        $bundle = new ElasticallyBundle();
        $bundle->build($container);

        return $container;
    }
}
