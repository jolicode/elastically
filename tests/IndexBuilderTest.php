<?php

declare(strict_types=1);

namespace JoliCode\Elastically\Tests;

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

        $this->expectException(\Elastica\Exception\InvalidException::class);
        $indexBuilder->createIndex('wrongname');
    }

    public function testCanCreateIndexWithEmptyMapping(): void
    {
        $indexBuilder = $this->getIndexBuilder();

        $index = $indexBuilder->createIndex('empty');
        $this->assertInstanceOf(\Elastica\Index::class, $index);

        $mapping = $index->getMapping();
        $this->assertEmpty($mapping);
    }

    public function testCanCreateIndexWithoutAnalysis(): void
    {
        $indexBuilder = $this->getIndexBuilder();

        $index = $indexBuilder->createIndex('beers');
        $this->assertInstanceOf(\Elastica\Index::class, $index);

        $mapping = $index->getMapping();

        $this->assertArrayHasKey('properties', $mapping);
        $this->assertArrayHasKey('name', $mapping['properties']);
        $this->assertSame('english', $mapping['properties']['name']['analyzer']);

        $aliases = $index->getAliases();
        $this->assertEmpty($aliases);

        $settings = $index->getSettings();
        $this->assertInstanceOf(\Elastica\Index\Settings::class, $settings);
        $this->assertEmpty($settings->get('analysis'));
    }

    public function testCanCreateIndexWithAnalysis(): void
    {
        $indexBuilder = $this->getIndexBuilder(__DIR__.'/configs_analysis');

        $index = $indexBuilder->createIndex('hop');
        $this->assertInstanceOf(\Elastica\Index::class, $index);

        $settings = $index->getSettings();
        $this->assertInstanceOf(\Elastica\Index\Settings::class, $settings);

        $this->assertIsArray($settings->get('analysis'));
        $this->assertNotEmpty($settings->get('analysis'));
    }

    public function testGetBackThePureIndexName(): void
    {
        $indexBuilder = $this->getIndexBuilder(__DIR__.'/configs_analysis');

        $index = $indexBuilder->createIndex('hop');
        $this->assertInstanceOf(\Elastica\Index::class, $index);

        $this->assertNotEquals('hop', $index->getName());
        $this->assertEquals('hop', IndexBuilder::getPureIndexName($index->getName()));
    }
}
