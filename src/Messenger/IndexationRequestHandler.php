<?php

namespace JoliCode\Elastically\Messenger;

use Elastica\Exception\Bulk\ResponseException;
use Elastica\Exception\RuntimeException;
use JoliCode\Elastically\Client;
use JoliCode\Elastically\Indexer;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class IndexationRequestHandler implements MessageHandlerInterface
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

    private $client;

    private $bus;

    private $exchanger;

    public function __construct(Client $client, MessageBusInterface $bus, DocumentExchangerInterface $exchanger)
    {
        $this->client = $client;
        $this->bus = $bus;
        $this->exchanger = $exchanger;

        // Disable the logs for memory concerns
        $this->client->setLogger(new NullLogger());
    }

    public function __invoke(IndexationRequestInterface $message)
    {
        $messages = [];
        if ($message instanceof MultipleIndexationRequest) {
            $messages = $message->getOperations();
        } elseif ($message instanceof IndexationRequest) {
            $messages = [$message];
        }

        $indexer = $this->client->getIndexer();

        $messageOffset = 0;
        $responseOffset = $indexer->getQueueSize();

        try {
            foreach ($messages as $indexationRequest) {
                ++$messageOffset;
                $this->schedule($indexer, $indexationRequest);

                if (0 === $indexer->getQueueSize()) {
                    $responseOffset = 0;
                }
            }

            $indexer->flush();
        } catch (ResponseException $exception) {
            // Extracts failed operations from the bulk
            // Responses are checked in reverse mode because we have only requests from the last bulk
            $failedMessages = [];
            $allResponses = $exception->getResponseSet()->getBulkResponses();
            $concernedResponses = array_reverse(array_slice($allResponses, $responseOffset));
            $executedMessages = array_reverse(array_slice($messages, 0, $messageOffset));
            foreach ($concernedResponses as $key => $response) {
                if (!$response->isOk()) {
                    array_unshift($failedMessages, $executedMessages[$key]);
                }
            }

            // Throws exception as-is if all operations have failed
            if (count($failedMessages) === count($messages)) {
                throw $exception;
            }

            // Redispatch failed and non-executed messages
            $nonExecutedMessages = array_slice($messages, $messageOffset);
            if (count($nonExecutedMessages) > 1) {
                $nonExecutedMessages = [new MultipleIndexationRequest($nonExecutedMessages)];
            }
            $toRedispatch = array_merge($failedMessages, $nonExecutedMessages);
            foreach ($toRedispatch as $indexationRequest) {
                $this->bus->dispatch($indexationRequest);
            }
        }
    }

    private function schedule(Indexer $indexer, IndexationRequest $indexationRequest)
    {
        try {
            $indexName = $this->client->getIndexNameFromClass($indexationRequest->getClassName());
        } catch (RuntimeException $e) {
            throw new UnrecoverableMessageHandlingException('Cannot guess the Index for this request. Dropping the message.', 0, $e);
        }

        if (self::OP_DELETE === $indexationRequest->getOperation()) {
            $indexer->scheduleDelete($indexName, $indexationRequest->getId());

            return;
        }

        $document = $this->exchanger->fetchDocument($indexationRequest->getClassName(), $indexationRequest->getId());

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
