<?php

/*
 * This file is part of the jolicode/elastically library.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JoliCode\Elastically\Tests\Symfony;

use JoliCode\Elastically\Bridge\Symfony\DataCollector\ElasticallyDataCollector;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ProfilerTest extends WebTestCase
{
    public function testRequestsAreCollected(): void
    {
        $client = self::createClient();
        $client->enableProfiler();
        $client->request('GET', '/with_search');

        $this->assertResponseIsSuccessful();

        $collector = $client->getProfile()->getCollector('elastically');
        $this->assertInstanceOf(ElasticallyDataCollector::class, $collector);
        $this->assertSame(3, $collector->getRequestCount());
        $this->assertSame(1, $collector->getDuplicateCount());
        $this->assertSame(1, $collector->getErrorCount());
        $this->assertSame(['default', 'special'], array_keys($collector->getConnections()));

        $requests = $collector->getConnections()['default']['requests'];
        $this->assertSame('HEAD', $requests[0]['method']);
        $this->assertSame('/hop', $requests[0]['path']);
        $this->assertSame('POST', $requests[2]['method']);
        $this->assertSame('/hop/_search', $requests[2]['path']);

        $client->request('GET', \sprintf('/_profiler/%s?panel=elastically', $client->getProfile()->getToken()));

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('#collector-content h2', 'Elastically');
        $this->assertSelectorCount(3, '.sf-profiler-elastically-requests tbody tr');
        $this->assertSelectorExists('[data-clipboard-text^="curl -X POST"]');
        $this->assertSelectorTextContains('.sf-profiler-elastically-requests', 'Duplicated ×2');
    }

    public function testHydratedModelsAreCollected(): void
    {
        $client = self::createClient();
        $client->enableProfiler();
        $client->request('GET', '/with_hydration');

        $this->assertResponseIsSuccessful();

        $collector = $client->getProfile()->getCollector('elastically');
        $this->assertInstanceOf(ElasticallyDataCollector::class, $collector);
        $this->assertSame(2, $collector->getModelCount());

        $connection = $collector->getConnections()['default'];
        $this->assertContains(['name' => 'profiled_beers', 'prefixed_name' => 'profiled_beers', 'class' => ProfiledBeer::class], $connection['indices']);

        $hydrations = array_values(array_filter($connection['requests'], static fn (array $request): bool => $request['model_count'] > 0));
        $this->assertCount(2, $hydrations);
        $this->assertSame('/profiled_beers/_search', $hydrations[0]['path']);
        $this->assertSame('/profiled_beers/_doc/1', $hydrations[1]['path']);
        $this->assertSame(ProfiledBeer::class, $hydrations[0]['models'][0]['class']);
        $this->assertSame('1', $hydrations[0]['models'][0]['id']);

        $client->request('GET', \sprintf('/_profiler/%s?panel=elastically', $client->getProfile()->getToken()));

        $this->assertResponseIsSuccessful();
        $this->assertSelectorCount(2, '.sf-profiler-elastically-requests .request-models');
        $this->assertSelectorTextContains('.sf-profiler-elastically-requests', 'View hydrated models (1)');
        $this->assertSelectorTextContains('.sf-profiler-elastically-requests .request-models', 'ProfiledBeer');
        $this->assertSelectorTextContains('.sf-profiler-elastically-requests .request-models', 'Kwak');
    }

    protected static function getKernelClass(): string
    {
        return ProfilerTestKernel::class;
    }
}
