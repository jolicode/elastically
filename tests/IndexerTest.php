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

use Elastica\Bulk\ResponseSet;
use Elastica\Document;
use Elastica\Exception\Bulk\ResponseException;
use JoliCode\Elastically\Client;
use JoliCode\Elastically\Indexer;

final class IndexerTest extends BaseTestCase
{
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
        $document = $client->getIndex($indexName)->getDocument('f');

        $this->assertInstanceOf(Document::class, $document);
        $this->assertSame('f', $document->getId());
    }

    public function testIndexOneDocumentWithMapping(): void
    {
        $indexName = mb_strtolower(__FUNCTION__);
        $client = $this->getClient();
        $client->setConfigValue(Client::CONFIG_INDEX_CLASS_MAPPING, [
            $indexName => TestDTO::class,
        ]);

        $dto = new TestDTO();
        $dto->bar = 'I like unicorns.';
        $dto->foo = 'Why is the sky blue?';

        $indexer = $client->getIndexer();

        $indexer->scheduleIndex($indexName, new Document('f', $dto));
        $indexer->flush();

        $indexer->refresh($indexName);

        $model = $client->getIndex($indexName)->getModel('f');

        $this->assertInstanceOf(TestDTO::class, $model);
        $this->assertSame($dto->bar, $model->bar);
        $this->assertSame($dto->foo, $model->foo);
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
            $indexer->scheduleIndex($indexName, new Document((string) $i, $dto));
        }

        // 3 bulks should have been sent, leaving only one document
        $this->assertSame(1, $indexer->getQueueSize());

        $indexer->flush();

        $this->assertSame(0, $indexer->getQueueSize());
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

        $indexer->scheduleCreate($indexName, new Document('1', $dto));
        $indexer->scheduleUpdate($indexName, new Document('1', $dto));
        $indexer->scheduleIndex($indexName, new Document('1', $dto));
        $indexer->scheduleDelete($indexName, '1');

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
            $indexer->scheduleCreate($indexName, new Document('1', $dto));
            $indexer->scheduleCreate($indexName, new Document('1', $dto));
            $indexer->scheduleCreate($indexName, new Document('1', $dto));
            $indexer->scheduleCreate($indexName, new Document('1', $dto));

            $this->assertFalse(true, 'Exception should have been thrown.');
        } catch (ResponseException $exception) {
            $response = $exception->getResponseSet();
        }

        $this->assertInstanceOf(ResponseSet::class, $response);
        $this->assertTrue($response->hasError());
        $this->assertSame(0, $indexer->getQueueSize());
    }

    public function testRequestParameters(): void
    {
        $indexName = mb_strtolower(__FUNCTION__);
        $dto = new TestDTO();
        $dto->bar = 'I like unicorns.';
        $dto->foo = 'Why is the sky blue?';

        $client = $this->getClient();
        $client->setConfigValue(Client::CONFIG_BULK_SIZE, 3);
        $indexer = $client->getIndexer();
        $indexer->setBulkRequestParams([
            'refresh' => 'wait_for',
        ]);

        $indexer->scheduleIndex($indexName, new Document('1', $dto));
        $response = $indexer->flush();

        $this->assertInstanceOf(ResponseSet::class, $response);
        $transferInfo = $response->getTransferInfo();
        $this->assertStringContainsString('_bulk?refresh=wait_for', $transferInfo['url']);

        // Test the same with an invalid pipeline
        $indexer->setBulkRequestParams([
            'pipeline' => 'covfefe',
        ]);

        $indexer->scheduleIndex($indexName, new Document('1', $dto));

        $this->expectException(ResponseException::class);
        $this->expectExceptionMessageRegExp('/pipeline with id \[covfefe\] does not exist/');

        $indexer->flush();
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

    public function testCustomSerializerContext(): void
    {
        $indexName = mb_strtolower(__FUNCTION__);

        $client = $this->getClient();
        $client->setConfigValue(Client::CONFIG_INDEX_CLASS_MAPPING, [
            $indexName => TestDTO::class,
        ]);
        $client->setConfigValue(Client::CONFIG_SERIALIZER_CONTEXT_PER_CLASS, [
            TestDTO::class => ['attributes' => ['foo']],
        ]);

        $dto = new TestDTO();
        $dto->bar = 'I like unicorns.';
        $dto->foo = 'Why is the sky blue?';

        $indexer = $client->getIndexer();
        $indexer->scheduleIndex($indexName, new Document('f', $dto));
        $indexer->flush();

        $indexer->refresh($indexName);

        $model = $client->getIndex($indexName)->getModel('f');

        $this->assertInstanceOf(TestDTO::class, $model);
        $this->assertSame($dto->foo, $model->foo);
        $this->assertEmpty($model->bar);

        // Also work on read
        $client->setConfigValue(Client::CONFIG_SERIALIZER_CONTEXT_PER_CLASS, [
            TestDTO::class => ['attributes' => ['yolo']],
        ]);

        $model = $client->getIndex($indexName)->getModel('f');

        $this->assertInstanceOf(TestDTO::class, $model);
        $this->assertEmpty($model->foo);
        $this->assertEmpty($model->bar);
    }

    private function getIndexer($path = null): Indexer
    {
        return $this->getClient($path)->getIndexer();
    }
}

class TestDTO
{
    public $foo;
    public $bar;
}
