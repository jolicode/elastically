<?php

/*
 * This file is part of the jolicode/elastically library.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JoliCode\Elastically\Bridge\Symfony\EventListener;

use JoliCode\Elastically\Indexer;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;

/**
 * Logs an error when an Indexer still holds operations that were never sent to
 * Elasticsearch, which usually means a call to Indexer::flush() is missing.
 */
class UnflushedIndexerListener implements EventSubscriberInterface
{
    /**
     * @param iterable<string, Indexer> $indexers Indexers keyed by connection name
     */
    public function __construct(
        private readonly iterable $indexers,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function checkQueues(): void
    {
        if (!$this->logger) {
            return;
        }

        foreach ($this->indexers as $connection => $indexer) {
            $queueSize = $indexer->getQueueSize();

            if (0 === $queueSize) {
                continue;
            }

            $this->logger->error('The Indexer of the "{connection}" connection still has {queue_size} operation(s) that were never sent to Elasticsearch. Did you forget to call Indexer::flush()?', [
                'connection' => $connection,
                'queue_size' => $queueSize,
            ]);
        }
    }

    public static function getSubscribedEvents(): array
    {
        // Low priority, so listeners flushing the Indexer on these events run first
        return [
            KernelEvents::TERMINATE => ['checkQueues', -1024],
            ConsoleEvents::TERMINATE => ['checkQueues', -1024],
            WorkerMessageHandledEvent::class => ['checkQueues', -1024],
            WorkerMessageFailedEvent::class => ['checkQueues', -1024],
        ];
    }
}
