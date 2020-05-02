<?php

declare(strict_types=1);

namespace JoliCode\Elastically\Tests;

use Elastica\Exception\InvalidException;
use Elastica\Exception\ResponseException;
use Elastica\Index;
use Elastica\Index\Settings;
use JoliCode\Elastically\Client;
use JoliCode\Elastically\IndexBuilder;

final class IndexBuilderTest extends BaseTestCase
{
    private function getIndexBuilder($path = null): IndexBuilder
    {
        return $this->getClient($path)->getIndexBuilder();
    }

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
        $indexBuilder = $this->getIndexBuilder(__DIR__.'/configs_analysis');

        $index = $indexBuilder->createIndex('hop');
        $this->assertInstanceOf(Index::class, $index);

        $settings = $index->getSettings();
        $this->assertInstanceOf(Settings::class, $settings);

        $this->assertIsArray($settings->get('analysis'));
        $this->assertNotEmpty($settings->get('analysis'));
    }

    public function testPrefixedIndexName(): void
    {
        $client = $this->getClient(__DIR__.'/configs_analysis');
        $client->setConfigValue(Client::CONFIG_INDEX_PREFIX, 'hip');
        $indexBuilder = $client->getIndexBuilder();

        $index = $indexBuilder->createIndex('hop');

        $this->assertStringStartsWith('hip_hop', $index->getName());
    }

    public function testCohabitationOfPrefixedIndexName(): void
    {
        $client1 = $this->getClient(__DIR__.'/configs_analysis');
        $client1->setConfigValue(Client::CONFIG_INDEX_PREFIX, 'hip');

        $indexBuilder = $client1->getIndexBuilder();
        $index1 = $indexBuilder->createIndex('hop');

        $this->assertStringStartsWith('hip_hop', $index1->getName());

        $client2 = $this->getClient(__DIR__.'/configs_analysis');
        $client2->setConfigValue(Client::CONFIG_INDEX_PREFIX, 'testing');

        $indexBuilder = $client2->getIndexBuilder();
        $index2 = $indexBuilder->createIndex('hop');

        $this->assertStringStartsWith('testing_hop', $index2->getName());

        $this->assertTrue($index1->exists());
        $this->assertTrue($index2->exists());
    }

    public function testGetBackThePureIndexName(): void
    {
        $client = $this->getClient(__DIR__.'/configs_analysis');
        $indexBuilder = $client->getIndexBuilder();

        $index = $indexBuilder->createIndex('hop');
        $this->assertInstanceOf(Index::class, $index);

        $this->assertNotEquals('hop', $index->getName());
        $this->assertEquals('hop', $client->getPureIndexName($index->getName()));
    }

    public function testGetBackThePureIndexNamePrefixed(): void
    {
        $client = $this->getClient(__DIR__.'/configs_analysis');
        $client->setConfigValue(Client::CONFIG_INDEX_PREFIX, 'hip');
        $indexBuilder = $client->getIndexBuilder();

        $index = $indexBuilder->createIndex('hop');
        $this->assertInstanceOf(Index::class, $index);

        $this->assertNotEquals('hop', $index->getName());
        $this->assertEquals('hop', $client->getPureIndexName($index->getName()));
    }

    public function testPurgeAndCloseOldIndices(): void
    {
        $indexBuilder = $this->getIndexBuilder(__DIR__.'/configs_analysis');

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

    public function testPurgerDistinguishesIndicesWithTheSamePrefix(): void
    {
        $indexBuilder = $this->getIndexBuilder(__DIR__.'/configs_analysis');

        $indexFooBar = $indexBuilder->createIndex('foo_bar');
        $indexBuilder->markAsLive($indexFooBar, 'foo_bar');

        usleep(1200000); // 1,2 second

        $indexFoo = $indexBuilder->createIndex('foo');
        $indexBuilder->markAsLive($indexFoo, 'foo');

        $operations = $indexBuilder->purgeOldIndices('foo');

        $this->assertCount(0, $operations);
    }
}
