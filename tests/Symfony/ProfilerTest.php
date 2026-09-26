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

    protected static function getKernelClass(): string
    {
        return ProfilerTestKernel::class;
    }
}
