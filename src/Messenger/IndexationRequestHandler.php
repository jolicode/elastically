<?php

namespace JoliCode\Elastically\Messenger;

use Elastica\Document;
use JoliCode\Elastically\Client;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

abstract class IndexationRequestHandler implements MessageHandlerInterface
{
    const OP_INDEX = 'index';
    const OP_DELETE = 'delete';
    const OP_UPDATE = 'update';
    const OP_CREATE = 'create';

    const OPS = [
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

    public function __invoke(IndexationRequest $message)
    {
        $indexer = $this->client->getIndexer();
        $indexToClass = $this->client->getConfig(Client::CONFIG_INDEX_CLASS_MAPPING);
        $indexName = array_search($message->getType(), $indexToClass, true);

        if (!$indexName) {
            throw new UnrecoverableMessageHandlingException(sprintf('The given type (%s) does not exist!', $message->getType()));
        }

        if (self::OP_DELETE === $message->getOp()) {
            $indexer->scheduleDelete($indexName, $message->getId());
            $indexer->flush();

            return;
        }

        $model = $this->fetchModel($message->getType(), $message->getId());

        if (!$model) {
            // ID does not exists, delete
            $indexer->scheduleDelete($indexName, $message->getId());
            $indexer->flush();

            return;
        }

        switch ($message->getOp()) {
            case self::OP_INDEX:
                $indexer->scheduleIndex($indexName, new Document($message->getId(), $model, '_doc'));
                break;
            case self::OP_CREATE:
                $indexer->scheduleCreate($indexName, new Document($message->getId(), $model, '_doc'));
                break;
            case self::OP_UPDATE:
                $indexer->scheduleUpdate($indexName, new Document($message->getId(), $model, '_doc'));
                break;
        }

        $indexer->flush();
    }

    /**
     * Return a model (DTO) to send for indexation.
     */
    abstract public function fetchModel(string $type, string $id);
}
