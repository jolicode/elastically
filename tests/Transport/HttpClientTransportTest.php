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

namespace JoliCode\Elastically\Tests\Transport;

use Elastica\Exception\ExceptionInterface;
use Elastica\ResultSet;
use JoliCode\Elastically\Client;
use JoliCode\Elastically\Model\Document;
use JoliCode\Elastically\Factory;
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

        $indexer = $this->getFactory(null, [
            'transport' => new HttpClientTransport(HttpClient::create()),
        ])->buildIndexer();

        $indexer->scheduleIndex($indexName, new Document('1', $dto));
        $indexer->scheduleIndex($indexName, new Document('2', $dto));
        $indexer->scheduleIndex($indexName, new Document('3', $dto));

        $responseSet = $indexer->flush();

        $this->assertTrue($responseSet->isOk());
    }

    public function testCreateIndex(): void
    {
        $indexBuilder = $this->getFactory(null, [
            Factory::CONFIG_MAPPINGS_DIRECTORY => __DIR__ . '/../configs',
            'log' => false,
            'transport' => new HttpClientTransport(HttpClient::create()),
        ])->buildIndexBuilder();

        $index = $indexBuilder->createIndex('beers');
        $response = $indexBuilder->markAsLive($index, 'beers');

        $this->assertTrue($response->isOk());
    }

    public function testHttpClientIsCalledOnSearch()
    {
        $responses = [
            new MockResponse(
                <<<'JSON'
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

        $client = $this->getClient(null, [
            'log' => false,
            'transport' => new HttpClientTransport(new MockHttpClient($responses)),
        ]);

        $results = $client->getIndex(__FUNCTION__)->search();

        $this->assertInstanceOf(ResultSet::class, $results);
        $this->assertSame(0, $results->getTotalHits());
    }

    public function testHttpClientHandleErrorIdentically()
    {
        $clientHttpClient = $this->getClient(null, [
            'log' => false,
            'transport' => new HttpClientTransport(HttpClient::create()),
        ]);

        $clientNativeTransport = $this->getClient(null, [
            'log' => false,
            'transport' => new HttpClientTransport(HttpClient::create()),
        ]);

        $this->runOnBothAndCompare($clientHttpClient, $clientNativeTransport);

        $clientHttpClient = $this->getClient(null, [
            'log' => false,
            'host' => 'MALFORMED:828282',
            'transport' => new HttpClientTransport(HttpClient::create()),
        ]);

        $clientNativeTransport = $this->getClient(null, [
            'host' => 'MALFORMED:828282',
            'log' => false,
        ]);

        $this->runOnBothAndCompare($clientHttpClient, $clientNativeTransport);

        $clientHttpClient = $this->getClient(null, [
            'log' => false,
            'proxy' => '127.0.0.1:9292',
            'transport' => new HttpClientTransport(HttpClient::create()),
        ]);

        $clientNativeTransport = $this->getClient(null, [
            'proxy' => '127.0.0.1:9292',
            'log' => false,
        ]);

        $this->runOnBothAndCompare($clientHttpClient, $clientNativeTransport);
    }

    private function runOnBothAndCompare(Client $clientHttpClient, Client $clientNative)
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
        $this->assertInstanceOf(\get_class($httpClientException), $nativeException);
    }
}

class TestDTO
{
    public $foo;
    public $bar;
}
