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
use Elastica\Document as ElasticaDocument;
use Elastica\Index;
use JoliCode\Elastically\Model\Document;
use JoliCode\Elastically\Serializer\ContextBuilderInterface;
use JoliCode\Elastically\Serializer\StaticContextBuilder;
use Symfony\Component\Serializer\SerializerInterface;

class Indexer
{
    private Client $client;
    private SerializerInterface $serializer;
    private int $bulkMaxSize;
    private array $bulkRequestParams;
    private ContextBuilderInterface $contextBuilder;

    private ?Bulk $currentBulk = null;

    public function __construct(Client $client, SerializerInterface $serializer, int $bulkMaxSize = 100, array $bulkRequestParams = [], ContextBuilderInterface $contextBuilder = null)
    {
        // TODO: on the destruct, maybe throw an exception for non empty indexer queues?

        $this->client = $client;
        $this->serializer = $serializer;
        $this->bulkMaxSize = $bulkMaxSize;
        $this->bulkRequestParams = $bulkRequestParams;
        $this->contextBuilder = $contextBuilder ?? new StaticContextBuilder();
    }

    public function scheduleIndex($index, ElasticaDocument $document)
    {
        $document->setIndex($index instanceof Index ? $index->getName() : $index);
        $this->updateDocumentData($document);

        $this->getCurrentBulk()->addDocument($document, Bulk\Action::OP_TYPE_INDEX);

        $this->flushIfNeeded();
    }

    public function scheduleDelete($index, string $id)
    {
        $document = new Document($id);
        $document->setIndex($index instanceof Index ? $index->getName() : $index);
        $this->getCurrentBulk()->addAction(new Bulk\Action\DeleteDocument($document));

        $this->flushIfNeeded();
    }

    public function scheduleUpdate($index, ElasticaDocument $document)
    {
        $document->setIndex($index instanceof Index ? $index->getName() : $index);
        $this->updateDocumentData($document);

        $this->getCurrentBulk()->addDocument($document, Bulk\Action::OP_TYPE_UPDATE);

        $this->flushIfNeeded();
    }

    public function scheduleCreate($index, ElasticaDocument $document)
    {
        $document->setIndex($index instanceof Index ? $index->getName() : $index);
        $this->updateDocumentData($document);

        $this->getCurrentBulk()->addDocument($document, Bulk\Action::OP_TYPE_CREATE);

        $this->flushIfNeeded();
    }

    public function flush(): ?Bulk\ResponseSet
    {
        if (!$this->currentBulk) {
            return null;
        }

        if (0 === $this->getQueueSize()) {
            return null;
        }

        try {
            $response = $this->getCurrentBulk()->send();
        } finally {
            $this->currentBulk = null;
        }

        return $response;
    }

    public function getQueueSize()
    {
        if (!$this->currentBulk) {
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
        if (!$this->currentBulk) {
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

    private function updateDocumentData(ElasticaDocument $document): void
    {
        if ($document instanceof Document && null !== $document->getModel()) {
            $context = $this->contextBuilder->buildContext(\get_class($document->getModel()));
            $data = $this->serializer->serialize($document->getModel(), 'json', $context);
            $document->setData($data);

            return;
        }

        // This check is added for BC-compatibility with older version
        // But a deprecation could be added and this could be removed in 2.x versions (?)
        if (\is_object($document->getData())) { // @phpstan-ignore-line
            $context = $this->contextBuilder->buildContext(\get_class($document->getData())); // @phpstan-ignore-line
            $data = $this->serializer->serialize($document->getData(), 'json', $context);
            $document->setData($data);
        }
    }
}
