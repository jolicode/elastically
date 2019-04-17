<?php

declare(strict_types=1);

namespace JoliCode\Elastically\Tests;

use Elastica\Bulk\ResponseSet;
use Elastica\Document;
use Elastica\Exception\Bulk\ResponseException;
use JoliCode\Elastically\Client;
use JoliCode\Elastically\Indexer;

final class IndexerTest extends BaseTestCase
{
    private function getIndexer($path = null): Indexer
    {
        return $this->getClient($path)->getIndexer();
    }

    public function testIndexOneDocument(): void
    {
        $indexName = mb_strtolower(__FUNCTION__);

        $dto = new TestDTO();
        $dto->bar = 'I like unicorns.';
        $dto->foo = 'Why is the sky blue?';

        $indexer = $this->getIndexer();

        $indexer->scheduleIndex($indexName, new Document('f', $dto));
        $indexer->flush();

        $indexer->refresh($indexName);

        $client = $this->getClient();
        $document = $client->getIndex($indexName)->getDocument('f'); // @todo Document DATA is not the DTO here, call getModel?

        $this->assertInstanceOf(Document::class, $document);
        $this->assertEquals('f', $document->getId());
    }

    public function testIndexMultipleDocuments(): void
    {
        $indexName = mb_strtolower(__FUNCTION__);

        $dto = new TestDTO();
        $dto->bar = 'I like unicorns.';
        $dto->foo = 'Why is the sky blue?';

        $client = $this->getClient();
        $client->setConfigValue(Client::CONFIG_BULK_SIZE, 10);
        $indexer = $client->getIndexer();

        for ($i = 1; $i <= 31; ++$i) {
            $indexer->scheduleIndex($indexName, new Document($i, $dto));
        }

        // 3 bulks should have been sent, leaving only one document
        $this->assertEquals(1, $indexer->getQueueSize());

        $indexer->flush();

        $this->assertEquals(0, $indexer->getQueueSize());
    }

    public function testAllIndexerOperations(): void
    {
        $indexName = mb_strtolower(__FUNCTION__);

        $dto = new TestDTO();
        $dto->bar = 'I like unicorns.';
        $dto->foo = 'Why is the sky blue?';

        $client = $this->getClient();
        $client->setConfigValue(Client::CONFIG_BULK_SIZE, 10);
        $indexer = $client->getIndexer();

        $indexer->scheduleCreate($indexName, new Document(1, $dto));
        $indexer->scheduleUpdate($indexName, new Document(1, $dto));
        $indexer->scheduleIndex($indexName, new Document(1, $dto));
        $indexer->scheduleDelete($indexName, 1);

        $response = $indexer->flush();

        $this->assertInstanceOf(ResponseSet::class, $response);
        $this->assertFalse($response->hasError());
    }

    public function testIndexingWithError(): void
    {
        $indexName = mb_strtolower(__FUNCTION__);

        $dto = new TestDTO();
        $dto->bar = 'I like unicorns.';
        $dto->foo = 'Why is the sky blue?';

        $client = $this->getClient();
        $client->setConfigValue(Client::CONFIG_BULK_SIZE, 3);
        $indexer = $client->getIndexer();

        try {
            $indexer->scheduleCreate($indexName, new Document(1, $dto));
            $indexer->scheduleCreate($indexName, new Document(1, $dto));
            $indexer->scheduleCreate($indexName, new Document(1, $dto));
            $indexer->scheduleCreate($indexName, new Document(1, $dto));

            $this->assertFalse(true, 'Exception should have been thrown.');
        } catch (ResponseException $exception) {
            $response = $exception->getResponseSet();
        }

        $this->assertInstanceOf(ResponseSet::class, $response);
        $this->assertTrue($response->hasError());
        $this->assertEquals(0, $indexer->getQueueSize());
    }

    public function testIndexJsonString(): void
    {
        $indexName = mb_strtolower(__FUNCTION__);

        $indexer = $this->getIndexer();

        $indexer->scheduleIndex($indexName, new Document('f',
            json_encode(['foo' => 'I love unicorns.', 'bar' => 'I think PHP is better than butter.'])
        ));

        $response = $indexer->flush();

        $this->assertInstanceOf(ResponseSet::class, $response);
        $this->assertFalse($response->hasError());
    }
}

class TestDTO
{
    public $foo;
    public $bar;
}
