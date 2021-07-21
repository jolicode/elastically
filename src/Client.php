<?php

/*
 * This file is part of the jolicode/elastically library.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JoliCode\Elastically;

use Elastica\Client as ElasticaClient;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;

class Client extends ElasticaClient
{
    /* Elastically config keys // BC Layer, to remove in 2.0 */
    public const CONFIG_MAPPINGS_DIRECTORY = Factory::CONFIG_MAPPINGS_DIRECTORY;
    public const CONFIG_SERIALIZER_CONTEXT_PER_CLASS = Factory::CONFIG_SERIALIZER_CONTEXT_PER_CLASS;
    public const CONFIG_SERIALIZER = Factory::CONFIG_SERIALIZER;
    public const CONFIG_BULK_SIZE = Factory::CONFIG_BULK_SIZE;
    public const CONFIG_INDEX_PREFIX = Factory::CONFIG_INDEX_PREFIX;
    public const CONFIG_INDEX_CLASS_MAPPING = Factory::CONFIG_INDEX_CLASS_MAPPING;

    private Factory $factory;
    private ResultSetBuilder $resultSetBuilder;
    private IndexNameMapper $indexNameMapper;

    public function __construct($config = [], ?callable $callback = null, ?LoggerInterface $logger = null, ?ResultSetBuilder $resultSetBuilder = null, ?IndexNameMapper $indexNameMapper = null)
    {
        parent::__construct($config, $callback, $logger);

        // BC Layer, to remove in 2.0
        $config[Factory::CONFIG_CLIENT] = $this;
        $this->factory = new Factory($config);
        if (!$resultSetBuilder) {
            trigger_deprecation('jolicode/elastically', '1.4.0', 'Passing null as #4 argument of %s() is deprecated. Inject a %s instance instead.', __METHOD__, ResultSetBuilder::class);
        }
        $this->resultSetBuilder = $resultSetBuilder ?? $this->factory->buildBuilder();
        if (!$indexNameMapper) {
            trigger_deprecation('jolicode/elastically', '1.4.0', 'Passing null as #5 argument of %s() is deprecated. Inject a %s instance instead.', __METHOD__, IndexNameMapper::class);
        }
        $this->indexNameMapper = $indexNameMapper ?? $this->factory->buildBuilder();
        // End of BC Layer
    }

    /**
     * Return an elastically index.
     *
     * @return Index
     */
    public function getIndex(string $name): \Elastica\Index
    {
        $name = $this->indexNameMapper->getPrefixedIndex($name);

        return new Index($this, $name, $this->resultSetBuilder);
    }

    public function getPrefixedIndex(string $name): string
    {
        trigger_deprecation('jolicode/elastically', '1.4.0', 'Method %s() is deprecated. Use %s::%s() instead.', __METHOD__, IndexNameMapper::class, __FUNCTION__);

        return $this->indexNameMapper->getPrefixedIndex($name);
    }

    public function getIndexNameFromClass(string $className): string
    {
        trigger_deprecation('jolicode/elastically', '1.4.0', 'Method %s() is deprecated. Use %s::%s() instead.', __METHOD__, IndexNameMapper::class, __FUNCTION__);

        return $this->indexNameMapper->getIndexNameFromClass($className);
    }

    public function getClassFromIndexName(string $indexName): string
    {
        trigger_deprecation('jolicode/elastically', '1.4.0', 'Method %s() is deprecated. Use %s::%s() instead.', __METHOD__, IndexNameMapper::class, __FUNCTION__);

        return $this->indexNameMapper->getClassFromIndexName($indexName);
    }

    public function getPureIndexName(string $fullIndexName): string
    {
        trigger_deprecation('jolicode/elastically', '1.4.0', 'Method %s() is deprecated. Use %s::%s() instead.', __METHOD__, IndexNameMapper::class, __FUNCTION__);

        return $this->indexNameMapper->getPureIndexName($fullIndexName);
    }

    public function getIndexBuilder(): IndexBuilder
    {
        trigger_deprecation('jolicode/elastically', '1.4.0', 'Method %s() is deprecated. Inject a IndexBuilder instance in your code directly using dependency injection or call the %s.', __METHOD__, Factory::class);

        return $this->factory->buildIndexBuilder();
    }

    public function getIndexer(): Indexer
    {
        trigger_deprecation('jolicode/elastically', '1.4.0', 'Method %s() is deprecated. Inject a Indexer instance in your code directly using dependency injection or call the %s.', __METHOD__, Factory::class);

        return $this->factory->buildIndexer();
    }

    public function getBuilder(): ResultSetBuilder
    {
        trigger_deprecation('jolicode/elastically', '1.4.0', 'Method %s() is deprecated. Inject a ResultSetBuilder instance in your code directly using dependency injection or call the %s.', __METHOD__, Factory::class);

        return $this->factory->buildBuilder();
    }

    public function getSerializer(): SerializerInterface
    {
        return $this->factory->buildSerializer();
    }

    public function getDenormalizer(): DenormalizerInterface
    {
        trigger_deprecation('jolicode/elastically', '1.4.0', 'Method %s() is deprecated. Inject a SerializerInterface instance in your code directly using dependency injection or call the %s.', __METHOD__, Factory::class);

        return $this->factory->buildDenormalizer();
    }

    public function getSerializerContext(string $class): array
    {
        trigger_deprecation('jolicode/elastically', '1.4.0', 'Method %s() is deprecated. Inject a DenormalizerInterface instance in your code directly using dependency injection or call the %s.', __METHOD__, Factory::class);

        return $this->factory->buildSerializerContext($class);
    }
}
