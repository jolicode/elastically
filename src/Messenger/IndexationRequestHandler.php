<?php

namespace JoliCode\Elastically\Messenger;

use Elastica\Document;
use Elastica\Exception\RuntimeException;
use JoliCode\Elastically\Client;
use JoliCode\Elastically\Indexer;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

abstract class IndexationRequestHandler implements MessageHandlerInterface
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

    public function __construct(Client $client)
    {
        $this->client = $client;

        // Disable the logs for memory concerns
        $this->client->setLogger(new NullLogger());
    }

    public function __invoke(IndexationRequestInterface $message)
    {
        $indexer = $this->client->getIndexer();

        if ($message instanceof MultipleIndexationRequest) {
            foreach ($message->getOperations() as $operation) {
                $this->schedule($indexer, $operation);
            }
        }

        if ($message instanceof IndexationRequest) {
            $this->schedule($indexer, $message);
        }

        $indexer->flush();
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

        $model = $this->fetchModel($indexationRequest->getClassName(), $indexationRequest->getId());

        if (!$model) {
            // ID does not exists, delete
            $indexer->scheduleDelete($indexName, $indexationRequest->getId());

            return;
        }

        switch ($indexationRequest->getOperation()) {
            case self::OP_INDEX:
                $indexer->scheduleIndex($indexName, new Document($indexationRequest->getId(), $model, '_doc'));
                break;
            case self::OP_CREATE:
                $indexer->scheduleCreate($indexName, new Document($indexationRequest->getId(), $model, '_doc'));
                break;
            case self::OP_UPDATE:
                $indexer->scheduleUpdate($indexName, new Document($indexationRequest->getId(), $model, '_doc'));
                break;
        }
    }

    /**
     * Return a model (DTO) to send for indexation.
     */
    abstract public function fetchModel(string $className, string $id);
}
