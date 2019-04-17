<?php

namespace JoliCode\Elastically;

use Elastica\Bulk;
use Elastica\Client;
use Elastica\Document;
use Elastica\Index;

class Indexer
{
    /**
     * @var Client
     */
    protected $client;

    /**
     * @var array
     */
    protected $bulkInsertQueue = [];

    /**
     * @var array
     */
    protected $bulkDeleteQueue = [];

    /**
     * @var int
     */
    private $bulkSize;

    public function __construct(Client $client, $bulkSize = 100)
    {
        $this->client = $client;
        $this->bulkSize = $bulkSize;
    }

    /**
     * Add a Document to the current bulk.
     * This does not send the bulk! /!\ (only if the threshold is hit).
     *
     * @param Index|string $index
     */
    public function scheduleInsert($index, Document $document)
    {
        $document->setIndex($index instanceof Index ? $index->getName() : $index);
        $this->bulkInsertQueue[] = $document;

        if (\count($this->bulkInsertQueue) >= 100 || \count($this->bulkDeleteQueue) >= 100) {
            $this->flush();
        }
    }

    public function scheduleDelete($index, $id)
    {
        $document = new Document($id);
        $document->setIndex($index instanceof Index ? $index->getName() : $index);
        $this->bulkDeleteQueue[] = $document;

        if (\count($this->bulkInsertQueue) >= 100 || \count($this->bulkDeleteQueue) >= 100) {
            $this->flush();
        }
    }

    public function flush()
    {
        if (\count($this->bulkInsertQueue) > 0) {
            $bulk = new Bulk($this->client);
            $bulk->addDocuments($this->bulkInsertQueue);
            $this->bulkInsertQueue = [];

            $bulk->send();
        }

        if (\count($this->bulkDeleteQueue) > 0) {
            $bulk = new Bulk($this->client);
            $bulk->addDocuments($this->bulkDeleteQueue, Bulk\Action::OP_TYPE_DELETE);
            $this->bulkDeleteQueue = [];

            $bulk->send();
        }
    }
}