<?php

/*
 * This file is part of the jolicode/elastically library.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JoliCode\Elastically;

use Elastica\Bulk;
use Elastica\Document;
use Elastica\Exception\Bulk\ResponseException;
use Elastica\Index;
use Symfony\Component\Serializer\SerializerInterface;

class Indexer
{
    private $client;
    private $bulkMaxSize;
    private $serializer;
    /** @var Bulk|null */
    private $currentBulk = null;
    private $bulkRequestParams = [];

    public function __construct(Client $client, SerializerInterface $serializer, int $bulkMaxSize = 100, array $bulkRequestParams = [])
    {
        // TODO: on the destruct, maybe throw an exception for non empty indexer queues?

        $this->client = $client;
        $this->bulkMaxSize = $bulkMaxSize ?? 100;
        $this->serializer = $serializer;
        $this->bulkRequestParams = $bulkRequestParams;
    }

    public function scheduleIndex($index, Document $document)
    {
        $document->setIndex($index instanceof Index ? $index->getName() : $index);
        if (\is_object($document->getData())) {
            $context = $this->client->getSerializerContext(\get_class($document->getData()));
            $document->setData($this->serializer->serialize($document->getData(), 'json', $context));
        }

        $this->getCurrentBulk()->addDocument($document, Bulk\Action::OP_TYPE_INDEX);

        $this->flushIfNeeded();
    }

    public function scheduleDelete($index, string $id)
    {
        $document = new Document($id, '');
        $document->setIndex($index instanceof Index ? $index->getName() : $index);
        $this->getCurrentBulk()->addAction(new Bulk\Action\DeleteDocument($document));

        $this->flushIfNeeded();
    }

    public function scheduleUpdate($index, Document $document)
    {
        $document->setIndex($index instanceof Index ? $index->getName() : $index);
        if (\is_object($document->getData())) {
            $context = $this->client->getSerializerContext(\get_class($document->getData()));
            $document->setData($this->serializer->serialize($document->getData(), 'json', $context));
        }

        $this->getCurrentBulk()->addDocument($document, Bulk\Action::OP_TYPE_UPDATE);

        $this->flushIfNeeded();
    }

    public function scheduleCreate($index, Document $document)
    {
        $document->setIndex($index instanceof Index ? $index->getName() : $index);
        if (\is_object($document->getData())) {
            $context = $this->client->getSerializerContext(\get_class($document->getData()));
            $document->setData($this->serializer->serialize($document->getData(), 'json', $context));
        }

        $this->getCurrentBulk()->addDocument($document, Bulk\Action::OP_TYPE_CREATE);

        $this->flushIfNeeded();
    }

    public function flush(): ?Bulk\ResponseSet
    {
        if (null === $this->currentBulk) {
            return null;
        }

        if (0 === $this->getQueueSize()) {
            return null;
        }

        try {
            $response = $this->getCurrentBulk()->send();
        } catch (ResponseException $exception) {
            $this->currentBulk = null;

            throw $exception;
        }

        $this->currentBulk = null;

        return $response;
    }

    public function getQueueSize()
    {
        if (null === $this->currentBulk) {
            return 0;
        }

        return \count($this->currentBulk->getActions());
    }

    public function refresh($index)
    {
        $indexName = $index instanceof Index ? $index->getName() : $index;

        $this->client->getIndex($indexName)->refresh();
    }

    public function setBulkMaxSize(int $bulkMaxSize): void
    {
        $this->bulkMaxSize = $bulkMaxSize;

        if ($this->getQueueSize() > $bulkMaxSize) {
            $this->flush();
        }
    }

    public function getBulkRequestParams(): array
    {
        return $this->bulkRequestParams;
    }

    public function setBulkRequestParams(array $bulkRequestParams): void
    {
        $this->bulkRequestParams = $bulkRequestParams;
        $this->refreshBulkRequestParams();
    }

    protected function getCurrentBulk(): Bulk
    {
        if (!($this->currentBulk instanceof Bulk)) {
            $this->currentBulk = new Bulk($this->client);
            $this->refreshBulkRequestParams();
        }

        return $this->currentBulk;
    }

    protected function flushIfNeeded(): void
    {
        if ($this->getQueueSize() >= $this->bulkMaxSize) {
            $this->flush();
        }
    }

    private function refreshBulkRequestParams()
    {
        if (!$this->currentBulk) {
            return;
        }

        foreach ($this->bulkRequestParams as $key => $value) {
            $this->currentBulk->setRequestParam($key, $value);
        }
    }
}
