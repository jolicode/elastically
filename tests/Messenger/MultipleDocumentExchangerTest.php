<?php

/*
 * This file is part of the jolicode/elastically library.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JoliCode\Elastically\Tests\Messenger;

use Elastica\Bulk\ResponseSet;
use Elastica\Document as ElasticaDocument;
use JoliCode\Elastically\Indexer;
use JoliCode\Elastically\IndexNameMapper;
use JoliCode\Elastically\Messenger\IndexationRequest;
use JoliCode\Elastically\Messenger\IndexationRequestHandler;
use JoliCode\Elastically\Messenger\MultipleDocumentExchangerInterface;
use JoliCode\Elastically\Messenger\MultipleIndexationRequest;
use JoliCode\Elastically\Model\Document;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\MessageBus;

final class MultipleDocumentExchangerTest extends TestCase
{
    public function testDocumentsAreFetchedAtOnce(): void
    {
        $exchanger = new TestMultipleDocumentExchanger();
        $indexer = new SpyIndexer();

        $handler = new IndexationRequestHandler(new MessageBus(), $exchanger, $indexer, new IndexNameMapper(null, [
            'foo' => ExchangerFooDTO::class,
            'bar' => ExchangerBarDTO::class,
        ]));

        $handler(new MultipleIndexationRequest([
            new IndexationRequest(ExchangerFooDTO::class, '1'),
            new IndexationRequest(ExchangerFooDTO::class, '2', IndexationRequestHandler::OP_UPDATE),
            new IndexationRequest(ExchangerFooDTO::class, '1', targetIndex: 'foo_next'),
            new IndexationRequest(ExchangerFooDTO::class, '3', IndexationRequestHandler::OP_DELETE),
            new IndexationRequest(ExchangerBarDTO::class, '10', IndexationRequestHandler::OP_CREATE),
            new IndexationRequest(ExchangerBarDTO::class, 'missing'),
        ]));

        $this->assertSame([], $exchanger->singleCalls);
        $this->assertSame([
            [ExchangerFooDTO::class, ['1', '2']],
            [ExchangerBarDTO::class, ['10', 'missing']],
        ], $exchanger->multipleCalls);

        $this->assertSame([
            ['index', 'foo', '1'],
            ['update', 'foo', '2'],
            ['index', 'foo_next', '1'],
            ['delete', 'foo', '3'],
            ['create', 'bar', '10'],
            ['delete', 'bar', 'missing'],
        ], $indexer->operations);

        // The same fetched document is not shared between operations
        $this->assertNotSame($indexer->documents[0], $indexer->documents[2]);
        $this->assertSame('foo', $indexer->documents[0]->getIndex());
        $this->assertSame('foo_next', $indexer->documents[2]->getIndex());
    }

    public function testSingleRequestUsesFetchDocuments(): void
    {
        $exchanger = new TestMultipleDocumentExchanger();
        $indexer = new SpyIndexer();

        $handler = new IndexationRequestHandler(new MessageBus(), $exchanger, $indexer, new IndexNameMapper(null, [
            'foo' => ExchangerFooDTO::class,
        ]));

        $handler(new IndexationRequest(ExchangerFooDTO::class, '1'));

        $this->assertSame([], $exchanger->singleCalls);
        $this->assertSame([[ExchangerFooDTO::class, ['1']]], $exchanger->multipleCalls);
        $this->assertSame([['index', 'foo', '1']], $indexer->operations);
    }
}

class TestMultipleDocumentExchanger implements MultipleDocumentExchangerInterface
{
    public array $singleCalls = [];
    public array $multipleCalls = [];

    public function fetchDocument(string $className, string $id): ?ElasticaDocument
    {
        $this->singleCalls[] = [$className, $id];

        return null;
    }

    public function fetchDocuments(string $className, array $ids): iterable
    {
        $this->multipleCalls[] = [$className, $ids];

        foreach ($ids as $id) {
            if ('missing' === $id) {
                continue;
            }

            $dto = new $className();
            $dto->bar = $id;

            yield $id => new Document($id, $dto);
        }
    }
}

class SpyIndexer extends Indexer
{
    public array $operations = [];
    public array $documents = [];

    public function __construct()
    {
    }

    public function scheduleIndex($index, ElasticaDocument $document): void
    {
        $this->record('index', $index, $document);
    }

    public function scheduleUpdate($index, ElasticaDocument $document): void
    {
        $this->record('update', $index, $document);
    }

    public function scheduleCreate($index, ElasticaDocument $document): void
    {
        $this->record('create', $index, $document);
    }

    public function scheduleDelete($index, string $id): void
    {
        $this->operations[] = ['delete', $index, $id];
    }

    public function flush(): ?ResponseSet
    {
        return null;
    }

    public function getQueueSize(): int
    {
        return 0;
    }

    private function record(string $operation, string $index, ElasticaDocument $document): void
    {
        $document->setIndex($index);
        $this->operations[] = [$operation, $index, $document->getId()];
        $this->documents[] = $document;
    }
}

class ExchangerFooDTO
{
    public $bar;
}

class ExchangerBarDTO
{
    public $bar;
}
