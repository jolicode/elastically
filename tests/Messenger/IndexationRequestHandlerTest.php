<?php

declare(strict_types=1);

namespace JoliCode\Elastically\Tests\Messenger;

use Elastica\Document;
use Elastica\Exception\Bulk\ResponseException;
use Elastica\ResultSet;
use JoliCode\Elastically\Client;
use JoliCode\Elastically\Messenger\DocumentExchangerInterface;
use JoliCode\Elastically\Messenger\IndexationRequest;
use JoliCode\Elastically\Messenger\IndexationRequestHandler;
use JoliCode\Elastically\Messenger\IndexationRequestInterface;
use JoliCode\Elastically\Messenger\MultipleIndexationRequest;
use JoliCode\Elastically\Tests\BaseTestCase;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\TraceableMessageBus;

final class IndexationRequestHandlerTest extends BaseTestCase
{
    public function testDocumentAreIndexed(): void
    {
        $indexName = mb_strtolower(__FUNCTION__);

        $client = $this->getClient();
        $client->setConfigValue(Client::CONFIG_INDEX_CLASS_MAPPING, [
            $indexName => TestDTO::class,
        ]);

        $handler = new IndexationRequestHandler($client, new MessageBus(), new TestDocumentExchanger());
        $handler(new IndexationRequest(TestDTO::class, '1234567890'));
        $handler(new IndexationRequest(TestDTO::class, '1234567890', IndexationRequestHandler::OP_UPDATE));
        $handler(new IndexationRequest(TestDTO::class, 'ref7777', IndexationRequestHandler::OP_CREATE));
        $handler(new IndexationRequest(TestDTO::class, 'ref8888', IndexationRequestHandler::OP_INDEX));
        $handler(new IndexationRequest(TestDTO::class, 'ref9999', IndexationRequestHandler::OP_DELETE));

        $index = $client->getIndex($indexName);
        $index->refresh();

        $resultSet = $index->search();

        $this->assertInstanceOf(ResultSet::class, $resultSet);
        $this->assertEquals(3, $resultSet->getTotalHits());

        $this->assertInstanceOf(TestDTO::class, $index->getModel('1234567890'));
        $this->assertInstanceOf(TestDTO::class, $index->getModel('ref7777'));
        $this->assertInstanceOf(TestDTO::class, $index->getModel('ref8888'));
    }

    public function testGroupedMessagesAreHandled(): void
    {
        $handler = $this->getMockBuilder(IndexationRequestHandler::class)
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $handler
            ->expects($this->exactly(2))
            ->method('__invoke')
        ;

        $bus = new MessageBus([
            new HandleMessageMiddleware(new HandlersLocator([
                IndexationRequestInterface::class => [$handler],
            ])),
        ]);

        $bus->dispatch(new IndexationRequest(TestDTO::class, '1234567890'));
        $bus->dispatch(new MultipleIndexationRequest([
            new IndexationRequest(TestDTO::class, '1234567892'),
            new IndexationRequest(TestDTO::class, '1234567894'),
        ]));
    }

    public function testRequeueOnlyFailedMessageFromMultiple(): void
    {
        $indexNameWritable = mb_strtolower(__FUNCTION__).'_writable';
        $indexNameReadonly = mb_strtolower(__FUNCTION__).'_readonly';

        $client = $this->getClient();
        $client->setConfigValue(Client::CONFIG_INDEX_CLASS_MAPPING, [
            $indexNameWritable => FooDTO::class,
            $indexNameReadonly => BarDTO::class,
        ]);

        $indexReadonly = $client->getIndex($indexNameReadonly);
        $indexReadonly->create();
        $indexReadonly->close();

        $traceableBus = new TraceableMessageBus(new MessageBus());

        $handler = new IndexationRequestHandler($client, $traceableBus, new FooBarDocumentExchanger());

        $goodRequest = new IndexationRequest(FooDTO::class, '1234567892');
        $badRequest = new IndexationRequest(BarDTO::class, '1234567892');
        $multiRequest = new MultipleIndexationRequest([$goodRequest, $badRequest]);

        $handler($multiRequest);

        $dispatchedMessages = $traceableBus->getDispatchedMessages();

        $this->assertCount(1, $dispatchedMessages);
        $this->assertSame($badRequest, $dispatchedMessages[0]['message']);
    }

    public function testThrowsResponseExceptionIfAllRequestsFailInMulti(): void
    {
        $indexNameReadonly = mb_strtolower(__FUNCTION__).'_readonly';

        $client = $this->getClient();
        $client->setConfigValue(Client::CONFIG_INDEX_CLASS_MAPPING, [
            $indexNameReadonly => BarDTO::class,
        ]);

        $indexReadonly = $client->getIndex($indexNameReadonly);
        $indexReadonly->create();
        $indexReadonly->close();

        $traceableBus = new TraceableMessageBus(new MessageBus());

        $handler = new IndexationRequestHandler($client, $traceableBus, new FooBarDocumentExchanger());

        $badRequest1 = new IndexationRequest(BarDTO::class, '1234567892');
        $badRequest2 = new IndexationRequest(BarDTO::class, '1234567892');
        $multiRequest = new MultipleIndexationRequest([$badRequest1, $badRequest2]);

        $this->expectException(ResponseException::class);

        $handler($multiRequest);
    }

    public function testThrowsResponseExceptionIfSingleRequestFail(): void
    {
        $indexNameReadonly = mb_strtolower(__FUNCTION__).'_readonly';

        $client = $this->getClient();
        $client->setConfigValue(Client::CONFIG_INDEX_CLASS_MAPPING, [
            $indexNameReadonly => BarDTO::class,
        ]);

        $indexReadonly = $client->getIndex($indexNameReadonly);
        $indexReadonly->create();
        $indexReadonly->close();

        $traceableBus = new TraceableMessageBus(new MessageBus());

        $handler = new IndexationRequestHandler($client, $traceableBus, new FooBarDocumentExchanger());

        $badRequest = new IndexationRequest(BarDTO::class, '1234567892');

        $this->expectException(ResponseException::class);

        $handler($badRequest);
    }

    public function testMultipleBulkResilienceOverError(): void
    {
        $indexNameWritable = mb_strtolower(__FUNCTION__).'_writable';
        $indexNameReadonly = mb_strtolower(__FUNCTION__).'_readonly';

        $client = $this->getClient();
        $client->getIndexer()->setBulkMaxSize(3);
        $client->setConfigValue(Client::CONFIG_INDEX_CLASS_MAPPING, [
            $indexNameWritable => FooDTO::class,
            $indexNameReadonly => BarDTO::class,
        ]);

        $indexReadonly = $client->getIndex($indexNameReadonly);
        $indexReadonly->create();
        $indexReadonly->close();

        $traceableBus = new TraceableMessageBus(new MessageBus());

        $handler = new IndexationRequestHandler($client, $traceableBus, new FooBarDocumentExchanger());

        // bulk 1
        $request1 = new IndexationRequest(FooDTO::class, 'bulk-1-message-1');
        $request2 = new IndexationRequest(FooDTO::class, 'bulk-1-message-2');
        $request3 = new IndexationRequest(FooDTO::class, 'bulk-1-message-3');

        // bulk 2
        $request4bad = new IndexationRequest(BarDTO::class, 'bulk-2-message-4');
        $request5 = new IndexationRequest(FooDTO::class, 'bulk-2-message-5');
        $request6bad = new IndexationRequest(BarDTO::class, 'bulk-2-message-6');

        // bulk 3
        $request7 = new IndexationRequest(FooDTO::class, 'bulk-3-message-7');
        $request8 = new IndexationRequest(FooDTO::class, 'bulk-3-message-8');

        $multiRequest = new MultipleIndexationRequest([
            // bulk 1
            $request1,    // successfully executed
            $request2,    // successfully executed
            $request3,    // successfully executed
            // bulk 2
            $request4bad, // failed
            $request5,    // successfully executed
            $request6bad, // failed
            // bulk 3
            $request7,    // non-executed
            $request8,    // non-executed
        ]);

        $handler($multiRequest);

        $redispatched = $traceableBus->getDispatchedMessages();

        $this->assertCount(3, $redispatched);

        $this->assertSame($request4bad, $redispatched[0]['message']);
        $this->assertSame($request6bad, $redispatched[1]['message']);
        $this->assertInstanceOf(MultipleIndexationRequest::class, $redispatched[2]['message']);
        $this->assertSame($request7, $redispatched[2]['message']->getOperations()[0]);
        $this->assertSame($request8, $redispatched[2]['message']->getOperations()[1]);
    }
}

class TestDTO
{
    public $foo;
    public $bar;
}

class TestDocumentExchanger implements DocumentExchangerInterface
{
    public function fetchDocument(string $className, string $id): Document
    {
        $dto = new TestDTO();
        $dto->bar = 'todo';
        $dto->foo = $id;

        return new Document($id, $dto);
    }
}

class FooDTO
{
    public $foo;
}

class BarDTO
{
    public $bar;
}

class FooBarDocumentExchanger implements DocumentExchangerInterface
{
    public function fetchDocument(string $className, string $id): Document
    {
        $dto = new $className();
        $dto->bar = $id;

        return new Document($id, $dto);
    }
}
