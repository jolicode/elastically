<?php

/*
 * This file is part of the jolicode/elastically library.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JoliCode\Elastically\Tests\Bridge\Symfony\EventListener;

use JoliCode\Elastically\Bridge\Symfony\EventListener\UnflushedIndexerListener;
use JoliCode\Elastically\Indexer;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

final class UnflushedIndexerListenerTest extends TestCase
{
    public function testLogsAnErrorForEachIndexerWithAPendingQueue(): void
    {
        $logger = new TestLogger();

        $listener = new UnflushedIndexerListener([
            'default' => $this->createIndexer(2),
            'flushed' => $this->createIndexer(0),
            'another' => $this->createIndexer(1),
        ], $logger);

        $listener->checkQueues();

        $this->assertCount(2, $logger->logs);

        $this->assertSame('error', $logger->logs[0]['level']);
        $this->assertStringContainsString('Did you forget to call Indexer::flush()?', $logger->logs[0]['message']);
        $this->assertSame(['connection' => 'default', 'queue_size' => 2], $logger->logs[0]['context']);

        $this->assertSame('error', $logger->logs[1]['level']);
        $this->assertSame(['connection' => 'another', 'queue_size' => 1], $logger->logs[1]['context']);
    }

    public function testDoesNothingWhenAllQueuesAreEmpty(): void
    {
        $logger = new TestLogger();

        $listener = new UnflushedIndexerListener(['default' => $this->createIndexer(0)], $logger);
        $listener->checkQueues();

        $this->assertSame([], $logger->logs);
    }

    public function testDoesNotCheckQueuesWithoutLogger(): void
    {
        $indexer = $this->createMock(Indexer::class);
        $indexer->expects($this->never())->method('getQueueSize');

        $listener = new UnflushedIndexerListener(['default' => $indexer]);
        $listener->checkQueues();
    }

    private function createIndexer(int $queueSize): Indexer
    {
        $indexer = $this->createStub(Indexer::class);
        $indexer->method('getQueueSize')->willReturn($queueSize);

        return $indexer;
    }
}

class TestLogger extends AbstractLogger
{
    /** @var list<array{level: mixed, message: string, context: array}> */
    public array $logs = [];

    public function log($level, $message, array $context = []): void
    {
        $this->logs[] = ['level' => $level, 'message' => (string) $message, 'context' => $context];
    }
}
