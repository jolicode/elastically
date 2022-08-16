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

use JoliCode\Elastically\Mapping\MappingProviderInterface;
use JoliCode\Elastically\Mapping\YamlProvider;
use JoliCode\Elastically\Serializer\ContextBuilderInterface;
use JoliCode\Elastically\Serializer\StaticContextBuilder;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;

final class Factory
{
    // Elastically config keys
    public const CONFIG_BULK_SIZE = 'elastically_bulk_size';
    public const CONFIG_DENORMALIZER = 'elastically_denormalizer';
    public const CONFIG_INDEX_CLASS_MAPPING = 'elastically_index_class_mapping';
    public const CONFIG_INDEX_NAME_MAPPER = 'elastically_index_name_mapper';
    public const CONFIG_INDEX_PREFIX = 'elastically_index_prefix';
    public const CONFIG_MAPPINGS_DIRECTORY = 'elastically_mappings_directory';
    public const CONFIG_MAPPINGS_PROVIDER = 'elastically_mappings_provider';
    public const CONFIG_SERIALIZER = 'elastically_serializer';
    public const CONFIG_SERIALIZER_CONTEXT_BUILDER = 'elastically_serializer_context_builder';
    public const CONFIG_SERIALIZER_CONTEXT_PER_CLASS = 'elastically_serializer_context_per_class';

    private array $config;

    private Client $client;
    private IndexNameMapper $indexNameMapper;
    private IndexBuilder $indexBuilder;
    private Indexer $indexer;
    private ResultSetBuilder $resultSetBuilder;
    private ContextBuilderInterface $contextBuilder;
    private SerializerInterface $serializer;
    private DenormalizerInterface $denormalizer;
    private MappingProviderInterface $mappingProvider;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    public function setClient(Client $client): void
    {
        $this->client = $client;
    }

    public function buildClient(): Client
    {
        return $this->client ??= new Client(
            $this->config,
            null,
            null,
            $this->buildBuilder(),
            $this->buildIndexNameMapper()
        );
    }

    public function buildIndexNameMapper(): IndexNameMapper
    {
        if (isset($this->indexNameMapper)) {
            return $this->indexNameMapper;
        }

        if (\array_key_exists(self::CONFIG_INDEX_NAME_MAPPER, $this->config)) {
            return $this->indexNameMapper = $this->config[self::CONFIG_INDEX_NAME_MAPPER];
        }

        $prefix = $this->config[self::CONFIG_INDEX_PREFIX] ?? null;
        $indexClassMapping = $this->config[self::CONFIG_INDEX_CLASS_MAPPING] ?? [];

        return $this->indexNameMapper = new IndexNameMapper($prefix, $indexClassMapping);
    }

    public function buildIndexBuilder(): IndexBuilder
    {
        return $this->indexBuilder ??= new IndexBuilder($this->buildMappingProvider(), $this->buildClient(), $this->buildIndexNameMapper());
    }

    public function buildIndexer(): Indexer
    {
        return $this->indexer ??= new Indexer(
            $this->buildClient(),
            $this->buildSerializer(),
            $this->config[self::CONFIG_BULK_SIZE] ?? 100,
            [],
            $this->buildContextBuilder()
        );
    }

    public function buildBuilder(): ResultSetBuilder
    {
        return $this->resultSetBuilder ??= new ResultSetBuilder($this->buildIndexNameMapper(), $this->buildContextBuilder(), $this->buildDenormalizer());
    }

    public function buildSerializer(): SerializerInterface
    {
        return $this->serializer ??= $this->config[self::CONFIG_SERIALIZER] ?? $this->buildDefaultSerializer();
    }

    public function buildDenormalizer(): DenormalizerInterface
    {
        return $this->denormalizer ??= $this->config[self::CONFIG_DENORMALIZER] ?? $this->config[self::CONFIG_SERIALIZER] ?? $this->buildDefaultSerializer();
    }

    public function buildContextBuilder(): ContextBuilderInterface
    {
        return $this->contextBuilder ??= $this->config[self::CONFIG_SERIALIZER_CONTEXT_BUILDER] ?? new StaticContextBuilder($this->config[self::CONFIG_SERIALIZER_CONTEXT_PER_CLASS] ?? []);
    }

    public function buildSerializerContext(string $class): array
    {
        return $this->buildContextBuilder()->buildContext($class);
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

    private function buildMappingProvider(): MappingProviderInterface
    {
        return $this->mappingProvider ??= $this->config[self::CONFIG_MAPPINGS_PROVIDER] ?? new YamlProvider($this->config[self::CONFIG_MAPPINGS_DIRECTORY] ?? '');
    }
}
