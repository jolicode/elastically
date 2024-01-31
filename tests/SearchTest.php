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
use Elastica\Query;
use JoliCode\Elastically\Factory;
use JoliCode\Elastically\Model\Document;
use JoliCode\Elastically\Result;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;

final class SearchTest extends BaseTestCase
{
    public function testIndexAndSearch(): void
    {
        $indexName = mb_strtolower(__FUNCTION__);

        $factory = $this->getFactory(null, [
            Factory::CONFIG_INDEX_CLASS_MAPPING => [
                $indexName => SearchTestDto::class,
            ],
        ]);
        $indexer = $factory->buildIndexer();
        $client = $factory->buildClient();

        $dto = new SearchTestDto();
        $dto->bar = 'coucou unicorns';
        $dto->foo = '123';

        $indexer->scheduleIndex($indexName, new Document('f', $dto));
        $indexer->flush();

        $indexer->refresh($indexName);

        $this->assertInstanceOf(ElasticaDocument::class, $client->getIndex($indexName)->getDocument('f'));
        $this->assertInstanceOf(SearchTestDto::class, $client->getIndex($indexName)->getModel('f'));

        $results = $client->getIndex($indexName)->search('unicorns');

        $this->assertSame(1, $results->getTotalHits());

        $this->assertInstanceOf(Result::class, $results->getResults()[0]);
        $this->assertInstanceOf(ElasticaDocument::class, $results->getDocuments()[0]);
        $this->assertInstanceOf(SearchTestDto::class, $results->getResults()[0]->getModel());
    }

    public function testSearchWithSourceFilter(): void
    {
        $indexName = mb_strtolower(__FUNCTION__);

        $factory = $this->getFactory(null, [
            Factory::CONFIG_INDEX_CLASS_MAPPING => [
                $indexName => SearchTestDto::class,
            ],
        ]);
        $indexer = $factory->buildIndexer();
        $client = $factory->buildClient();

        $dto = new SearchTestDto();
        $dto->bar = 'coucou unicorns';
        $dto->foo = '123';

        $indexer->scheduleIndex($indexName, new Document('f', $dto));
        $indexer->flush();

        $indexer->refresh($indexName);

        $query = Query::create('coucou');
        $query->setSource(['foo']);
        $results = $client->getIndex($indexName)->search($query);

        $this->assertSame(1, $results->getTotalHits());

        $this->assertInstanceOf(Result::class, $results->getResults()[0]);
        $this->assertInstanceOf(ElasticaDocument::class, $results->getDocuments()[0]);
        $this->assertInstanceOf(SearchTestDto::class, $results->getResults()[0]->getModel());
        $this->assertNull($results->getResults()[0]->getModel()->bar);
        $this->assertNotNull($results->getResults()[0]->getModel()->foo);
    }

    public function testMyOwnSerializer(): void
    {
        $serializer = $this->createMock(SearchTestDummySerializer::class);
        $serializer->method('serialize')->willReturn('{"foo": "testMyOwnSerializer"}');
        $serializer->method('denormalize')->willReturn(new SearchTestDto());

        $indexName = mb_strtolower(__FUNCTION__);

        $factory = $this->getFactory(null, [
            Factory::CONFIG_INDEX_CLASS_MAPPING => [
                $indexName => SearchTestDto::class,
            ],
            Factory::CONFIG_SERIALIZER => $serializer,
        ]);
        $indexer = $factory->buildIndexer();
        $client = $factory->buildClient();

        $dto = new SearchTestDto();
        $dto->foo = 'testMyOwnSerializer';

        $indexer->scheduleIndex($indexName, new Document('f', $dto));
        $indexer->flush();

        $indexer->refresh($indexName);

        $results = $client->getIndex($indexName)->search('testMyOwnSerializer');

        $this->assertSame(1, $results->getTotalHits());

        $this->assertInstanceOf(Result::class, $results->getResults()[0]);
        $this->assertInstanceOf(ElasticaDocument::class, $results->getDocuments()[0]);
        $this->assertInstanceOf(SearchTestDto::class, $results->getResults()[0]->getModel());
    }
}

class SearchTestDummySerializer implements SerializerInterface, DenormalizerInterface
{
    public function denormalize($data, $class, $format = null, array $context = []): mixed
    {
    }

    public function supportsDenormalization($data, $type, $format = null, array $context = []): bool
    {
    }

    public function serialize(mixed $data, string $format, array $context = []): string
    {
    }

    public function deserialize(mixed $data, string $type, string $format, array $context = []): mixed
    {
    }

    public function getSupportedTypes(?string $format): array
    {
    }
}

class SearchTestDto
{
    public $foo;
    public $bar;
}
