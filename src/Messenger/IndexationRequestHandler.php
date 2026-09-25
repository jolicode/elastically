<?php

/*
 * This file is part of the jolicode/elastically library.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JoliCode\Elastically\Messenger;

use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\MissingParameterException;
use Elastic\Elasticsearch\Exception\ServerResponseException;
use Elastic\Transport\Exception\NoNodeAvailableException;
use Elastica\Document as ElasticaDocument;
use Elastica\Exception\Bulk\ResponseException;
use Elastica\Exception\ExceptionInterface;
use Elastica\Exception\RuntimeException;
use JoliCode\Elastically\Indexer;
use JoliCode\Elastically\IndexNameMapper;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Serializer\Exception\ExceptionInterface as SerializerExceptionInterface;

#[AsMessageHandler]
class IndexationRequestHandler
{
    public const OP_INDEX = 'index';
    public const OP_DELETE = 'delete';
    public const OP_UPDATE = 'update';
    public const OP_CREATE = 'create';

    public const OPERATIONS = [
        self::OP_INDEX,
        self::OP_DELETE,
        self::OP_UPDATE,
        self::OP_CREATE,
    ];

    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly DocumentExchangerInterface $exchanger,
        private readonly Indexer $indexer,
        private readonly IndexNameMapper $indexNameMapper,
    ) {
    }

    /**
     * @throws ExceptionInterface
     * @throws ClientResponseException
     * @throws MissingParameterException
     * @throws ServerResponseException
     * @throws NoNodeAvailableException
     * @throws \Symfony\Component\Messenger\Exception\ExceptionInterface
     * @throws SerializerExceptionInterface
     */
    public function __invoke(IndexationRequestInterface $message): void
    {
        $messages = [];
        if ($message instanceof MultipleIndexationRequest) {
            $messages = $message->getOperations();
        } elseif ($message instanceof IndexationRequest) {
            $messages = [$message];
        }

        $documents = $this->prefetchDocuments($messages);

        $messageOffset = 0;
        $responseOffset = $this->indexer->getQueueSize();

        try {
            foreach ($messages as $indexationRequest) {
                ++$messageOffset;
                $this->schedule($this->indexer, $indexationRequest, $documents);

                if (0 === $this->indexer->getQueueSize()) {
                    $responseOffset = 0;
                }
            }

            $this->indexer->flush();
        } catch (ResponseException $exception) {
            // Extracts failed operations from the bulk
            // Responses are checked in reverse mode because we have only requests from the last bulk
            $failedMessages = [];
            $allResponses = $exception->getResponseSet()->getBulkResponses();
            $concernedResponses = array_reverse(\array_slice($allResponses, $responseOffset));
            $executedMessages = array_reverse(\array_slice($messages, 0, $messageOffset));
            foreach ($concernedResponses as $key => $response) {
                if (!$response->isOk()) {
                    array_unshift($failedMessages, $executedMessages[$key]);
                }
            }

            // Throws exception as-is if all operations have failed
            if (\count($failedMessages) === \count($messages)) {
                throw $exception;
            }

            // Redispatch failed and non-executed messages
            $nonExecutedMessages = \array_slice($messages, $messageOffset);
            if (\count($nonExecutedMessages) > 1) {
                $nonExecutedMessages = [new MultipleIndexationRequest($nonExecutedMessages)];
            }
            $toRedispatch = array_merge($failedMessages, $nonExecutedMessages);
            foreach ($toRedispatch as $indexationRequest) {
                $this->bus->dispatch($indexationRequest);
            }
        }
    }

    /**
     * Fetch all the documents at once when the exchanger supports it.
     *
     * @param array<IndexationRequest> $messages
     *
     * @return array<string, array<string, ElasticaDocument|null>>|null Documents indexed by class name and ID, null if not supported
     */
    private function prefetchDocuments(array $messages): ?array
    {
        if (!$this->exchanger instanceof MultipleDocumentExchangerInterface) {
            return null;
        }

        $idsPerClass = [];
        foreach ($messages as $indexationRequest) {
            if (self::OP_DELETE === $indexationRequest->getOperation()) {
                continue;
            }

            $idsPerClass[$indexationRequest->getClassName()][$indexationRequest->getId()] = $indexationRequest->getId();
        }

        $documents = [];
        foreach ($idsPerClass as $className => $ids) {
            $documents[$className] = [];
            foreach ($this->exchanger->fetchDocuments($className, array_values($ids)) as $id => $document) {
                $documents[$className][(string) $id] = $document;
            }
        }

        return $documents;
    }

    /**
     * @param array<string, array<string, ElasticaDocument|null>>|null $documents
     *
     * @throws ClientResponseException
     * @throws ExceptionInterface
     * @throws MissingParameterException
     * @throws ServerResponseException
     * @throws NoNodeAvailableException
     * @throws UnrecoverableMessageHandlingException
     * @throws SerializerExceptionInterface
     */
    private function schedule(Indexer $indexer, IndexationRequest $indexationRequest, ?array $documents = null): void
    {
        $indexName = $indexationRequest->getTargetIndex();

        if (null === $indexName) {
            try {
                $indexName = $this->indexNameMapper->getIndexNameFromClass($indexationRequest->getClassName());
            } catch (RuntimeException $e) {
                throw new UnrecoverableMessageHandlingException('Cannot guess the Index for this request. Dropping the message.', 0, $e);
            }
        }

        if (self::OP_DELETE === $indexationRequest->getOperation()) {
            $indexer->scheduleDelete($indexName, $indexationRequest->getId());

            return;
        }

        if (null === $documents) {
            $document = $this->exchanger->fetchDocument($indexationRequest->getClassName(), $indexationRequest->getId());
        } else {
            $document = $documents[$indexationRequest->getClassName()][$indexationRequest->getId()] ?? null;
            // The same document can be scheduled many times (in different indexes for example), and the Indexer mutates it
            $document = $document ? clone $document : null;
        }

        if (!$document) {
            // ID does not exists, delete
            $indexer->scheduleDelete($indexName, $indexationRequest->getId());

            return;
        }

        $document->setId($indexationRequest->getId());

        switch ($indexationRequest->getOperation()) {
            case self::OP_INDEX:
                $indexer->scheduleIndex($indexName, $document);

                break;

            case self::OP_CREATE:
                $indexer->scheduleCreate($indexName, $document);

                break;

            case self::OP_UPDATE:
                $indexer->scheduleUpdate($indexName, $document);

                break;
        }
    }
}
