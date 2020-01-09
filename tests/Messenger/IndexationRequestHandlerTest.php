<?php

declare(strict_types=1);

namespace JoliCode\Elastically\Tests\Messenger;

use JoliCode\Elastically\Client;
use JoliCode\Elastically\Messenger\IndexationRequest;
use JoliCode\Elastically\Messenger\IndexationRequestHandler;
use JoliCode\Elastically\ResultSet;
use JoliCode\Elastically\Tests\BaseTestCase;

final class IndexationRequestHandlerTest extends BaseTestCase
{
    public function testDocumentAreIndexed(): void
    {
        $indexName = mb_strtolower(__FUNCTION__);

        $client = $this->getClient();
        $client->setConfigValue(Client::CONFIG_INDEX_CLASS_MAPPING, [
            $indexName => TestDTO::class,
        ]);

        $handler = new TestHandler($client);
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
}

class TestDTO
{
    public $foo;
    public $bar;
}

class TestHandler extends IndexationRequestHandler
{
    public function fetchModel(string $type, string $id)
    {
        $dto = new TestDTO();
        $dto->bar = 'todo';
        $dto->foo = $id;

        return $dto;
    }
}
