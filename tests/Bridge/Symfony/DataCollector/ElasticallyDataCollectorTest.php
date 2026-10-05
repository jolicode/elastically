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
use JoliCode\Elastically\Bridge\Symfony\DataCollector\TraceableResultSetBuilder;
use JoliCode\Elastically\IndexNameMapper;
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
    private TraceableResultSetBuilder $resultSetBuilder;
    private IndexNameMapper $indexNameMapper;

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

        $collector = $this->createCollector($client);
        $collector->lateCollect();

        $this->assertSame(4, $collector->getRequestCount());
        $this->assertSame(1, $collector->getErrorCount());
        $this->assertSame(1, $collector->getDuplicateCount());
        $this->assertGreaterThan(0, $collector->getDuration());

        $connection = $collector->getConnections()['default'];
        $this->assertSame(['http://localhost:9200'], $connection['hosts']);
        $this->assertSame([
            ['name' => 'beers', 'prefixed_name' => 'beers', 'class' => Beer::class],
            ['name' => 'missing', 'prefixed_name' => 'missing', 'class' => Beer::class],
        ], $connection['indices']);
        $this->assertCount(4, $connection['requests']);

        [$first, $second, $bulk, $error] = $connection['requests'];

        $this->assertSame('POST', $first['method']);
        $this->assertSame('/beers/_search', $first['path']);
        $this->assertSame(200, $first['status_code']);
        $this->assertSame(2, $first['occurrences']);
        $this->assertSame(2, $second['occurrences']);
        $this->assertFalse($first['is_error']);
        $this->assertSame("curl -X POST 'http://localhost:9200/beers/_search' -H 'Content-Type: application/json' --data-binary '{\"query\":{\"match_all\":{}}}'", $first['curl_command']);

        $this->assertSame([['name' => 'beers', 'pure_name' => 'beers', 'class' => Beer::class]], $first['indices']);
        $this->assertSame(0, $first['model_count']);

        $this->assertSame('/_bulk', $bulk['path']);
        $this->assertSame([['name' => 'beers', 'pure_name' => 'beers', 'class' => Beer::class]], $bulk['indices']);
        $this->assertSame(1, $bulk['occurrences']);
        $this->assertStringContainsString("-H 'Content-Type: application/x-ndjson'", $bulk['curl_command']);

        $this->assertSame(404, $error['status_code']);
        $this->assertTrue($error['is_error']);
        $this->assertNotNull($error['error']);
        $this->assertNotNull($error['response_body']);

        $collector->reset();

        $this->assertSame([], $client->getTraces());
        $this->assertSame([], $this->resultSetBuilder->getHydrations());
        $this->assertSame(0, $collector->getRequestCount());
        $this->assertSame([], $collector->getConnections());
    }

    public function testCollectHydratedModels(): void
    {
        $client = $this->createClient([
            $this->createResponse('{"took":1,"timed_out":false,"hits":{"total":{"value":2,"relation":"eq"},"hits":['
                . '{"_index":"dev_beers_2026-10-05-123456","_id":"1","_score":1,"_source":{"name":"Kwak"}},'
                . '{"_index":"dev_beers_2026-10-05-123456","_id":"2","_score":1,"_source":{"name":"Chimay"}}'
                . ']}}'),
            $this->createResponse('{"_index":"dev_beers_2026-10-05-123456","_id":"3","_version":1,"found":true,"_source":{"name":"Orval"}}'),
            $this->createResponse('{"took":1,"errors":false,"items":[]}'),
        ], 'dev');

        $client->getIndex('beers')->search(new Query(new Query\MatchAll()));
        $client->getIndex('beers')->getModel('3');
        $client->addDocuments([new Document('4', ['name' => 'Westmalle'], 'dev_beers_2026-10-05-123456')]);

        $collector = $this->createCollector($client);
        $collector->lateCollect();

        $this->assertSame(3, $collector->getModelCount());

        $connection = $collector->getConnections()['default'];
        $this->assertSame(['name' => 'beers', 'prefixed_name' => 'dev_beers', 'class' => Beer::class], $connection['indices'][0]);

        [$search, $get, $bulk] = $connection['requests'];

        $this->assertSame([['name' => 'dev_beers', 'pure_name' => 'beers', 'class' => Beer::class]], $search['indices']);
        $this->assertSame(2, $search['model_count']);
        $this->assertSame('dev_beers_2026-10-05-123456', $search['models'][0]['index']);
        $this->assertSame('beers', $search['models'][0]['pure_index']);
        $this->assertSame('1', $search['models'][0]['id']);
        $this->assertSame(Beer::class, $search['models'][0]['class']);
        $this->assertSame('Kwak', $search['models'][0]['model']->getValue(true)['name']);
        $this->assertSame('2', $search['models'][1]['id']);

        $this->assertSame(1, $get['model_count']);
        $this->assertSame('3', $get['models'][0]['id']);
        $this->assertSame('Orval', $get['models'][0]['model']->getValue(true)['name']);

        $this->assertSame([['name' => 'dev_beers_2026-10-05-123456', 'pure_name' => 'beers', 'class' => Beer::class]], $bulk['indices']);
        $this->assertSame(0, $bulk['model_count']);
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
    private function createClient(array $responses, ?string $prefix = null): TraceableClient
    {
        $this->indexNameMapper = new IndexNameMapper($prefix, ['beers' => Beer::class, 'missing' => Beer::class]);
        $this->resultSetBuilder = new TraceableResultSetBuilder($this->indexNameMapper, new StaticContextBuilder(), new Serializer([new ObjectNormalizer()]));

        $client = new TraceableClient(
            [
                'hosts' => ['http://elastic:secret@localhost:9200'],
                'transport_config' => [
                    'http_client' => new Psr18Client(new MockHttpClient($responses)),
                ],
            ],
            null,
            $this->resultSetBuilder,
            $this->indexNameMapper,
        );
        $this->resultSetBuilder->setClient($client);

        return $client;
    }

    private function createCollector(TraceableClient $client): ElasticallyDataCollector
    {
        $collector = new ElasticallyDataCollector();
        $collector->addClient('default', $client, $this->resultSetBuilder, $this->indexNameMapper);

        return $collector;
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

class Beer
{
    public string $name;
}
