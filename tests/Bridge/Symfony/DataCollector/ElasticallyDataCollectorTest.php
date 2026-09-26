<?php

/*
 * This file is part of the jolicode/elastically library.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JoliCode\Elastically\Tests\Bridge\Symfony\DataCollector;

use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastica\Document;
use Elastica\Query;
use JoliCode\Elastically\Bridge\Symfony\DataCollector\ElasticallyDataCollector;
use JoliCode\Elastically\Bridge\Symfony\DataCollector\TraceableClient;
use JoliCode\Elastically\IndexNameMapper;
use JoliCode\Elastically\ResultSetBuilder;
use JoliCode\Elastically\Serializer\StaticContextBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Psr18Client;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Stopwatch\Stopwatch;

class ElasticallyDataCollectorTest extends TestCase
{
    public function testCollect(): void
    {
        $search = '{"took":1,"timed_out":false,"hits":{"total":{"value":0,"relation":"eq"},"hits":[]}}';
        $client = $this->createClient([
            $this->createResponse($search),
            $this->createResponse($search),
            $this->createResponse('{"took":1,"errors":false,"items":[]}'),
            $this->createResponse('{"error":{"type":"index_not_found_exception"},"status":404}', 404),
        ]);

        $client->getIndex('beers')->search(new Query(new Query\MatchAll()));
        $client->getIndex('beers')->search(new Query(new Query\MatchAll()));
        $client->addDocuments([new Document('1', ['name' => 'Kwak'], 'beers'), new Document('2', ['name' => 'Chimay'], 'beers')]);

        try {
            $client->getIndex('missing')->search(new Query(new Query\MatchAll()));
            $this->fail('An exception should have been thrown.');
        } catch (ClientResponseException) {
        }

        $collector = new ElasticallyDataCollector();
        $collector->addClient('default', $client);
        $collector->lateCollect();

        $this->assertSame(4, $collector->getRequestCount());
        $this->assertSame(1, $collector->getErrorCount());
        $this->assertSame(1, $collector->getDuplicateCount());
        $this->assertGreaterThan(0, $collector->getDuration());

        $connection = $collector->getConnections()['default'];
        $this->assertSame(['http://localhost:9200'], $connection['hosts']);
        $this->assertCount(4, $connection['requests']);

        [$first, $second, $bulk, $error] = $connection['requests'];

        $this->assertSame('POST', $first['method']);
        $this->assertSame('/beers/_search', $first['path']);
        $this->assertSame(200, $first['status_code']);
        $this->assertSame(2, $first['occurrences']);
        $this->assertSame(2, $second['occurrences']);
        $this->assertFalse($first['is_error']);
        $this->assertSame(<<<'CONSOLE'
            POST /beers/_search
            {
                "query": {
                    "match_all": {}
                }
            }
            CONSOLE, $first['console_command']);
        $this->assertSame("curl -X POST 'http://localhost:9200/beers/_search' -H 'Content-Type: application/json' --data-binary '{\"query\":{\"match_all\":{}}}'", $first['curl_command']);

        $this->assertSame('/_bulk', $bulk['path']);
        $this->assertSame(1, $bulk['occurrences']);
        $this->assertStringContainsString("-H 'Content-Type: application/x-ndjson'", $bulk['curl_command']);
        $this->assertStringStartsWith("POST /_bulk\n{", $bulk['console_command']);
        $this->assertCount(5, explode("\n", $bulk['console_command']));

        $this->assertSame(404, $error['status_code']);
        $this->assertTrue($error['is_error']);
        $this->assertNotNull($error['error']);
        $this->assertNotNull($error['response_body']);

        $collector->reset();

        $this->assertSame([], $client->getTraces());
        $this->assertSame(0, $collector->getRequestCount());
        $this->assertSame([], $collector->getConnections());
    }

    public function testTraceableClientUsesTheStopwatch(): void
    {
        $client = $this->createClient([
            $this->createResponse('{"took":1,"timed_out":false,"hits":{"total":{"value":0,"relation":"eq"},"hits":[]}}'),
        ]);
        $client->setStopwatch($stopwatch = new Stopwatch());

        $client->getIndex('beers')->search(new Query(new Query\MatchAll()));

        $this->assertArrayHasKey('POST /beers/_search', $stopwatch->getSectionEvents('__root__'));
        $this->assertSame('elastically', $stopwatch->getEvent('POST /beers/_search')->getCategory());
    }

    /**
     * @param list<MockResponse> $responses
     */
    private function createClient(array $responses): TraceableClient
    {
        $indexNameMapper = new IndexNameMapper(null, ['beers' => \stdClass::class, 'missing' => \stdClass::class]);
        $serializer = new Serializer([new ObjectNormalizer()]);

        return new TraceableClient(
            [
                'hosts' => ['http://elastic:secret@localhost:9200'],
                'transport_config' => [
                    'http_client' => new Psr18Client(new MockHttpClient($responses)),
                ],
            ],
            null,
            new ResultSetBuilder($indexNameMapper, new StaticContextBuilder(), $serializer),
            $indexNameMapper,
        );
    }

    private function createResponse(string $body, int $status = 200): MockResponse
    {
        return new MockResponse($body, [
            'http_code' => $status,
            'response_headers' => [
                'Content-Type' => 'application/json',
                'X-Elastic-Product' => 'Elasticsearch',
            ],
        ]);
    }
}
