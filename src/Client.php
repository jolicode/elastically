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
use Elastica\Exception\RuntimeException;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;

class Client extends ElasticaClient
{
    /* Elastically config keys */
    public const CONFIG_MAPPINGS_DIRECTORY = 'elastically_mappings_directory';
    public const CONFIG_INDEX_CLASS_MAPPING = 'elastically_index_class_mapping';
    public const CONFIG_INDEX_PREFIX = 'elastically_index_prefix';
    public const CONFIG_SERIALIZER_CONTEXT_PER_CLASS = 'elastically_serializer_context_per_class';
    public const CONFIG_SERIALIZER = 'elastically_serializer';
    public const CONFIG_BULK_SIZE = 'elastically_bulk_size';

    private Indexer $indexer;
    private IndexBuilder $indexBuilder;
    private ResultSetBuilder $resultSetBuilder;
    private SerializerInterface $serializer;
    private DenormalizerInterface $denormalizer;

    public function getIndexBuilder(): IndexBuilder
    {
        if (!isset($this->indexBuilder)) {
            $this->indexBuilder = new IndexBuilder($this, $this->getConfig(self::CONFIG_MAPPINGS_DIRECTORY));
        }

        return $this->indexBuilder;
    }

    public function getIndexer(): Indexer
    {
        if (!isset($this->indexer)) {
            $this->indexer = new Indexer($this, $this->getSerializer(), $this->getConfigValue(self::CONFIG_BULK_SIZE, 100));
        }

        return $this->indexer;
    }

    public function getBuilder(): ResultSetBuilder
    {
        if (!isset($this->resultSetBuilder)) {
            $this->resultSetBuilder = new ResultSetBuilder($this);
        }

        return $this->resultSetBuilder;
    }

    /**
     * Return an elastically index.
     *
     * @return Index
     */
    public function getIndex(string $name): \Elastica\Index
    {
        $name = $this->getPrefixedIndex($name);

        return new Index($this, $name);
    }

    public function getPrefixedIndex(string $name): string
    {
        $prefix = $this->getConfigValue(self::CONFIG_INDEX_PREFIX);
        if ($prefix) {
            return sprintf('%s_%s', $prefix, $name);
        }

        return $name;
    }

    public function getIndexNameFromClass(string $className): string
    {
        $indexToClass = $this->getConfig(self::CONFIG_INDEX_CLASS_MAPPING);
        $indexName = array_search($className, $indexToClass, true);

        if (!$indexName) {
            throw new RuntimeException(sprintf('The given type (%s) does not exist in the configuration.', $className));
        }

        return $this->getPrefixedIndex($indexName);
    }

    public function getClassFromIndexName(string $indexName): string
    {
        $indexToClass = $this->getConfig(self::CONFIG_INDEX_CLASS_MAPPING);

        if (!isset($indexToClass[$indexName])) {
            throw new RuntimeException(sprintf('Unknown class for index "%s", did you forgot to configure "%s"?', $indexName, self::CONFIG_INDEX_CLASS_MAPPING));
        }

        return $indexToClass[$indexName];
    }

    public function getPureIndexName(string $fullIndexName): string
    {
        $prefix = $this->getConfigValue(self::CONFIG_INDEX_PREFIX);

        if ($prefix) {
            $pattern = sprintf('/%s_(.+)_\d{4}-\d{2}-\d{2}-\d+/i', preg_quote($prefix, '/'));
        } else {
            $pattern = '/(.+)_\d{4}-\d{2}-\d{2}-\d+/i';
        }

        if (1 === preg_match($pattern, $fullIndexName, $matches)) {
            return $matches[1];
        }

        return $fullIndexName;
    }

    public function getSerializer(): SerializerInterface
    {
        return $this->serializer ??= $this->getConfigValue(self::CONFIG_SERIALIZER) ?? $this->buildDefaultSerializer();
    }

    public function getDenormalizer(): DenormalizerInterface
    {
        return $this->denormalizer ??= $this->getConfigValue(self::CONFIG_SERIALIZER) ?? $this->buildDefaultSerializer();
    }

    public function getSerializerContext($class): array
    {
        $configSerializer = $this->getConfigValue(self::CONFIG_SERIALIZER_CONTEXT_PER_CLASS);

        return $configSerializer[$class] ?? [];
    }

    private function buildDefaultSerializer(): Serializer
    {
        // Use a minimal default serializer
        return new Serializer([
            new ArrayDenormalizer(),
            new DateTimeNormalizer(),
            new ObjectNormalizer(null, null, null, new PhpDocExtractor()),
        ], [
            new JsonEncoder(),
        ]);
    }
}
