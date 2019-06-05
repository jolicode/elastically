<?php

namespace JoliCode\Elastically;

use Elastica\Client as ElasticaClient;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;

class Client extends ElasticaClient
{
    /* Elastically config keys */
    const CONFIG_MAPPINGS_DIRECTORY = 'elastically_mappings_directory';
    const CONFIG_INDEX_CLASS_MAPPING = 'elastically_index_class_mapping';
    const CONFIG_INDEX_PREFIX = 'elastically_index_prefix';
    const CONFIG_SERIALIZER_CONTEXT_PER_CLASS = 'elastically_serializer_context_per_class';
    const CONFIG_SERIALIZER = 'elastically_serializer';
    const CONFIG_BULK_SIZE = 'elastically_bulk_size';

    private $indexer;

    public function getIndexBuilder(): IndexBuilder
    {
        return new IndexBuilder($this, $this->getConfig(self::CONFIG_MAPPINGS_DIRECTORY));
    }

    public function getIndexer(): Indexer
    {
        if (!$this->indexer) {
            $this->indexer = new Indexer($this, $this->getSerializer(), $this->getConfigValue(self::CONFIG_BULK_SIZE, 100));
        }

        return $this->indexer;
    }

    public function getIndex($name): Index
    {
        $name = $this->getConfigValue(self::CONFIG_INDEX_PREFIX).$name;

        return new Index($this, $name);
    }

    public function getSerializer(): SerializerInterface
    {
        $configSerializer = $this->getConfigValue(self::CONFIG_SERIALIZER);
        if ($configSerializer) {
            return $configSerializer;
        }

        // Use a minimal default serializer
        return new Serializer([
            new ArrayDenormalizer(),
            new DateTimeNormalizer(),
            new ObjectNormalizer(null, null, null, new PhpDocExtractor()),
        ], [
            new JsonEncoder(),
        ]);
    }

    public function getSerializerContext($class): array
    {
        $configSerializer = $this->getConfigValue(self::CONFIG_SERIALIZER_CONTEXT_PER_CLASS);

        return $configSerializer[$class] ?? [];
    }
}
