<?php

declare(strict_types=1);

/*
 * This file is part of the jolicode/elastically library.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JoliCode\Elastically\Tests;

use Elastica\Document as ElasticaDocument;
use Elastica\Exception\InvalidException;
use Elastica\Exception\ResponseException;
use Elastica\Index;
use Elastica\Index\Settings;
use JoliCode\Elastically\Factory;
use JoliCode\Elastically\IndexBuilder;
use JoliCode\Elastically\Model\Document;

final class IndexBuilderTest extends BaseTestCase
{
    public function testCannotCreateIndexWithoutMapping(): void
    {
        $indexBuilder = $this->getIndexBuilder();

        $this->expectException(InvalidException::class);
        $indexBuilder->createIndex('wrongname');
    }

    public function testCanCreateIndexWithEmptyMapping(): void
    {
        $indexBuilder = $this->getIndexBuilder();

        $index = $indexBuilder->createIndex('empty');
        $this->assertInstanceOf(Index::class, $index);

        $mapping = $index->getMapping();
        $this->assertEmpty($mapping);
    }

    public function testCanCreateIndexWithoutAnalysis(): void
    {
        $indexBuilder = $this->getIndexBuilder();

        $index = $indexBuilder->createIndex('beers');
        $this->assertInstanceOf(Index::class, $index);

        $mapping = $index->getMapping();

        $this->assertArrayHasKey('properties', $mapping);
        $this->assertArrayHasKey('name', $mapping['properties']);
        $this->assertSame('english', $mapping['properties']['name']['analyzer']);

        $aliases = $index->getAliases();
        $this->assertEmpty($aliases);

        $settings = $index->getSettings();
        $this->assertInstanceOf(Settings::class, $settings);
        $this->assertEmpty($settings->get('analysis'));
    }

    public function testCanCreateIndexWithAnalysis(): void
    {
        $indexBuilder = $this->getIndexBuilder(__DIR__ . '/configs_analysis');

        $index = $indexBuilder->createIndex('hop');
        $this->assertInstanceOf(Index::class, $index);

        $settings = $index->getSettings();
        $this->assertInstanceOf(Settings::class, $settings);

        $this->assertIsArray($settings->get('analysis'));
        $this->assertNotEmpty($settings->get('analysis'));
    }

    public function testPrefixedIndexName(): void
    {
        $indexBuilder = $this->getIndexBuilder(__DIR__ . '/configs_analysis', [
            Factory::CONFIG_INDEX_PREFIX => 'hip',
        ]);

        $index = $indexBuilder->createIndex('hop');

        $this->assertStringStartsWith('hip_hop', $index->getName());
    }

    public function testCohabitationOfPrefixedIndexName(): void
    {
        $indexBuilder = $this->getIndexBuilder(__DIR__ . '/configs_analysis', [
            Factory::CONFIG_INDEX_PREFIX => 'hip',
        ]);

        $index1 = $indexBuilder->createIndex('hop');

        $this->assertStringStartsWith('hip_hop', $index1->getName());

        $indexBuilder = $this->getIndexBuilder(__DIR__ . '/configs_analysis', [
            Factory::CONFIG_INDEX_PREFIX => 'testing',
        ]);

        $index2 = $indexBuilder->createIndex('hop');

        $this->assertStringStartsWith('testing_hop', $index2->getName());

        $this->assertTrue($index1->exists());
        $this->assertTrue($index2->exists());
    }

    public function testGetBackThePureIndexName(): void
    {
        $factory = $this->getFactory(__DIR__ . '/configs_analysis');

        $indexBuilder = $factory->buildIndexBuilder();
        $indexNameMapper = $factory->buildIndexNameMapper();

        $index = $indexBuilder->createIndex('hop');
        $this->assertInstanceOf(Index::class, $index);

        $this->assertNotSame('hop', $index->getName());
        $this->assertSame('hop', $indexNameMapper->getPureIndexName($index->getName()));
    }

    public function testGetBackThePureIndexNamePrefixed(): void
    {
        $factory = $this->getFactory(__DIR__ . '/configs_analysis', [
            Factory::CONFIG_INDEX_PREFIX => 'hip',
        ]);

        $indexBuilder = $factory->buildIndexBuilder();
        $indexNameMapper = $factory->buildIndexNameMapper();

        $index = $indexBuilder->createIndex('hop');
        $this->assertInstanceOf(Index::class, $index);

        $this->assertNotSame('hop', $index->getName());
        $this->assertSame('hop', $indexNameMapper->getPureIndexName($index->getName()));
    }

    /** @dataProvider purgeIndexBuilderProvider */
    public function testPurgeAndCloseOldIndices(IndexBuilder $indexBuilder): void
    {
        $index1 = $indexBuilder->createIndex('hop');
        $this->assertInstanceOf(Index::class, $index1);

        usleep(1200000); // 1,2 second

        $index2 = $indexBuilder->createIndex('hop');
        $this->assertInstanceOf(Index::class, $index2);

        usleep(1200000); // 1,2 second

        $index3 = $indexBuilder->createIndex('hop');
        $indexBuilder->markAsLive($index3, 'hop');
        $this->assertInstanceOf(Index::class, $index3);

        usleep(1200000); // 1,2 second

        $index4 = $indexBuilder->createIndex('hop');
        $this->assertInstanceOf(Index::class, $index4);

        $operations = $indexBuilder->purgeOldIndices('hop');

        $this->assertCount(2, $operations);

        $this->assertFalse($index1->exists());
        $this->assertTrue($index2->exists());
        $this->assertTrue($index3->exists());
        $this->assertTrue($index4->exists()); // Do not delete indexes in the future of the current one

        try {
            $index2->search();
            $this->assertFalse(true, 'Search should throw a "closed index" exception.');
        } catch (ResponseException $e) {
            $this->assertStringContainsStringIgnoringCase('closed', $e->getMessage());
        }
    }

    public function purgeIndexBuilderProvider(): \Generator
    {
        yield 'simple config' => [$this->getIndexBuilder(__DIR__ . '/configs_analysis')];

        yield 'with prefixed indices' => [$this->getIndexBuilder(__DIR__ . '/configs_analysis', [
            Factory::CONFIG_INDEX_PREFIX => 'hip',
        ])];
    }

    public function testPurgerDistinguishesIndicesWithTheSamePrefix(): void
    {
        $indexBuilder = $this->getIndexBuilder(__DIR__ . '/configs_analysis');

        $indexFooBar = $indexBuilder->createIndex('foo_bar');
        $indexBuilder->markAsLive($indexFooBar, 'foo_bar');

        usleep(1200000); // 1,2 second

        $indexFoo = $indexBuilder->createIndex('foo');
        $indexBuilder->markAsLive($indexFoo, 'foo');

        $operations = $indexBuilder->purgeOldIndices('foo');

        $this->assertCount(0, $operations);
    }

    public function testSlowDownRefresh(): void
    {
        $indexBuilder = $this->getIndexBuilder(__DIR__ . '/configs_analysis');
        $index = $indexBuilder->createIndex('hop');
        $indexBuilder->slowDownRefresh($index);
        $this->assertSame('60s', $index->getSettings()->getRefreshInterval());
    }

    public function testSpeedUpRefresh(): void
    {
        $indexBuilder = $this->getIndexBuilder(__DIR__ . '/configs_analysis');
        $index = $indexBuilder->createIndex('hop');
        $indexBuilder->speedUpRefresh($index);
        $this->assertSame('1s', $index->getSettings()->getRefreshInterval());
    }

    public function testMigrate(): void
    {
        $indexName = 'empty';

        $dto = new MigrateDTO();
        $dto->bar = 'I like unicorns.';
        $dto->foo = 'Why is the sky blue?';

        $indexer = $this->getFactory()->buildIndexer();

        $indexer->scheduleIndex($indexName, new Document('f', $dto));
        $indexer->flush();
        $indexer->refresh($indexName);

        $client = $this->getClient();
        $document = $client->getIndex($indexName)->getDocument('f');
        $this->assertInstanceOf(ElasticaDocument::class, $document);

        $indexBuilder = $this->getIndexBuilder(__DIR__ . '/configs');

        $newIndex = $indexBuilder->migrate($client->getIndex($indexName));
        $indexer->refresh($newIndex);

        $document = $newIndex->getDocument('f');
        $this->assertInstanceOf(ElasticaDocument::class, $document);
    }

    private function getIndexBuilder(?string $path = null, array $config = []): IndexBuilder
    {
        return $this->getFactory($path, $config)->buildIndexBuilder();
    }
}

class MigrateDTO
{
    public $foo;
    public $bar;
}
