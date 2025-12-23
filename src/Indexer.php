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

use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\MissingParameterException;
use Elastic\Elasticsearch\Exception\ServerResponseException;
use Elastic\Transport\Exception\NoNodeAvailableException;
use Elastica\Bulk;
use Elastica\Document as ElasticaDocument;
use Elastica\Exception\Bulk\ResponseException;
use Elastica\Exception\ClientException;
use Elastica\Index;
use JoliCode\Elastically\Model\Document;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\SerializerInterface;

class Indexer
{
    private Client $client;
    private SerializerInterface $serializer;
    private int $bulkMaxSize;
    private array $bulkRequestParams;

    private ?Bulk $currentBulk = null;

    public function __construct(Client $client, SerializerInterface $serializer, int $bulkMaxSize = 100, array $bulkRequestParams = [])
    {
        // TODO: on the destruct, maybe throw an exception for non empty indexer queues?

        $this->client = $client;
        $this->serializer = $serializer;
        $this->bulkMaxSize = $bulkMaxSize;
        $this->bulkRequestParams = $bulkRequestParams;
    }

    /**
     * @throws ClientResponseException
     * @throws ServerResponseException
     * @throws MissingParameterException
     * @throws NoNodeAvailableException
     * @throws ResponseException
     * @throws ClientException
     * @throws ExceptionInterface
     */
    public function scheduleIndex($index, ElasticaDocument $document): void
    {
        $document->setIndex($index instanceof Index ? $index->getName() : $index);
        $this->updateDocumentData($document);

        $this->getCurrentBulk()->addDocument($document, Bulk\Action::OP_TYPE_INDEX);

        $this->flushIfNeeded();
    }

    /**
     * @throws ClientResponseException
     * @throws ServerResponseException
     * @throws MissingParameterException
     * @throws NoNodeAvailableException
     * @throws ResponseException
     * @throws ClientException
     */
    public function scheduleDelete($index, string $id): void
    {
        $document = new Document($id);
        $document->setIndex($index instanceof Index ? $index->getName() : $index);
        $this->getCurrentBulk()->addAction(new Bulk\Action\DeleteDocument($document));

        $this->flushIfNeeded();
    }

    /**
     * @throws ClientResponseException
     * @throws ServerResponseException
     * @throws MissingParameterException
     * @throws NoNodeAvailableException
     * @throws ResponseException
     * @throws ClientException
     * @throws ExceptionInterface
     */
    public function scheduleUpdate($index, ElasticaDocument $document): void
    {
        $document->setIndex($index instanceof Index ? $index->getName() : $index);
        $this->updateDocumentData($document);

        $this->getCurrentBulk()->addDocument($document, Bulk\Action::OP_TYPE_UPDATE);

        $this->flushIfNeeded();
    }

    /**
     * @throws ClientResponseException
     * @throws ServerResponseException
     * @throws MissingParameterException
     * @throws NoNodeAvailableException
     * @throws ResponseException
     * @throws ClientException
     * @throws ExceptionInterface
     */
    public function scheduleCreate($index, ElasticaDocument $document): void
    {
        $document->setIndex($index instanceof Index ? $index->getName() : $index);
        $this->updateDocumentData($document);

        $this->getCurrentBulk()->addDocument($document, Bulk\Action::OP_TYPE_CREATE);

        $this->flushIfNeeded();
    }

    /**
     * @throws ClientException
     * @throws ResponseException
     * @throws ServerResponseException
     * @throws ClientResponseException
     * @throws MissingParameterException
     * @throws NoNodeAvailableException
     */
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

    public function getQueueSize(): int
    {
        if (!$this->currentBulk) {
            return 0;
        }

        return \count($this->currentBulk->getActions());
    }

    /**
     * @throws ClientException
     * @throws ClientResponseException
     * @throws MissingParameterException
     * @throws ServerResponseException
     * @throws NoNodeAvailableException
     */
    public function refresh(Index|string $index): void
    {
        $indexName = $index instanceof Index ? $index->getName() : $index;

        $this->client->getIndex($indexName)->refresh();
    }

    /**
     * @throws ClientException
     * @throws ClientResponseException
     * @throws MissingParameterException
     * @throws ServerResponseException
     * @throws NoNodeAvailableException
     * @throws ResponseException
     */
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

    /**
     * @throws ClientResponseException
     * @throws ClientException
     * @throws ResponseException
     * @throws ServerResponseException
     * @throws MissingParameterException
     * @throws NoNodeAvailableException
     */
    protected function flushIfNeeded(): void
    {
        if ($this->getQueueSize() >= $this->bulkMaxSize) {
            $this->flush();
        }
    }

    private function refreshBulkRequestParams(): void
    {
        if (!$this->currentBulk) {
            return;
        }

        foreach ($this->bulkRequestParams as $key => $value) {
            $this->currentBulk->setRequestParam($key, $value);
        }
    }

    /**
     * @throws ExceptionInterface
     */
    private function updateDocumentData(ElasticaDocument $document): void
    {
        if ($document instanceof Document && null !== $document->getModel()) {
            $data = $this->serializer->serialize($document->getModel(), 'json');
            $document->setData($data);
        }
    }
}
