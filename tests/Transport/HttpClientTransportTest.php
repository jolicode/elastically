<?php

declare(strict_types=1);

namespace JoliCode\Elastically\Tests\Transport;

use Elastica\Document;
use Elastica\Exception\ExceptionInterface;
use JoliCode\Elastically\Client;
use JoliCode\Elastically\ResultSet;
use JoliCode\Elastically\Tests\BaseTestCase;
use JoliCode\Elastically\Transport\HttpClientTransport;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class HttpClientTransportTest extends BaseTestCase
{
    public function testBulkPayload(): void
    {
        $indexName = mb_strtolower(__FUNCTION__);

        $dto = new TestDTO();
        $dto->bar = 'Roses are red';
        $dto->foo = 'Violets are blue';

        $client = new Client([
            'transport' => new HttpClientTransport(HttpClient::create()),
        ]);

        $indexer = $client->getIndexer();

        $indexer->scheduleIndex($indexName, new Document(1, $dto));
        $indexer->scheduleIndex($indexName, new Document(2, $dto));
        $indexer->scheduleIndex($indexName, new Document(3, $dto));

        $responseSet = $indexer->flush();

        $this->assertTrue($responseSet->isOk());
    }

    public function testHttpClientIsCalledOnSearch()
    {
        $responses = [
            new MockResponse(<<<JSON
{
  "took" : 1,
  "timed_out" : false,
  "hits" : {
    "total" : {
      "value" : 0,
      "relation" : "eq"
    },
    "max_score" : null,
    "hits" : [ ]
  }
}
JSON
),
        ];

        $client = new Client([
            'log' => false,
            'transport' => new HttpClientTransport(new MockHttpClient($responses)),
        ]);

        $results = $client->getIndex(__FUNCTION__)->search();

        $this->assertInstanceOf(ResultSet::class, $results);
        $this->assertEquals(0, $results->getTotalHits());
    }

    public function testHttpClientHandleErrorIdentically()
    {
        $clientHttpClient = new Client([
            'log' => false,
            'transport' => new HttpClientTransport(HttpClient::create()),
        ]);

        $clientNativeTransport = new Client([
            'log' => false,
        ]);

        $this->runOnBothAndCompare($clientHttpClient, $clientNativeTransport);

        $clientHttpClient = new Client([
            'log' => false,
            'host' => 'MALFORMED:828282',
            'transport' => new HttpClientTransport(HttpClient::create()),
        ]);

        $clientNativeTransport = new Client([
            'host' => 'MALFORMED:828282',
            'log' => false,
        ]);

        $this->runOnBothAndCompare($clientHttpClient, $clientNativeTransport);

        $clientHttpClient = new Client([
            'log' => false,
            'proxy' => '127.0.0.1:9292',
            'transport' => new HttpClientTransport(HttpClient::create()),
        ]);

        $clientNativeTransport = new Client([
            'proxy' => '127.0.0.1:9292',
            'log' => false,
        ]);

        $this->runOnBothAndCompare($clientHttpClient, $clientNativeTransport);
    }

    protected function runOnBothAndCompare(Client $clientHttpClient, Client $clientNative)
    {
        try {
            $clientHttpClient->getIndex(__FUNCTION__)->search();
            $this->assertFalse(true, 'No exception thrown by HttpClient!');

            return;
        } catch (\PHPUnit\Exception $e) {
            throw $e;
        } catch (\Exception $e) {
            $httpClientException = $e;
        }

        try {
            $clientNative->getIndex(__FUNCTION__)->search();

            $this->assertFalse(true, 'No exception thrown by Native Client!');

            return;
        } catch (\PHPUnit\Exception $e) {
            throw $e;
        } catch (\Exception $e) {
            $nativeException = $e;
        }

        $this->assertInstanceOf(ExceptionInterface::class, $nativeException);
        $this->assertInstanceOf(ExceptionInterface::class, $httpClientException);
        $this->assertSame(get_class($httpClientException), get_class($nativeException));
    }
}

class TestDTO
{
    public $foo;
    public $bar;
}
