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
use Elastica\Exception\ClientException;
use Elastica\Task;

/**
 * A reindex started by IndexBuilder::startMigration(), running on the Elasticsearch side.
 */
final class Migration
{
    public function __construct(
        public readonly Index $newIndex,
        public readonly Task $task,
    ) {
    }

    /**
     * @throws ClientResponseException
     * @throws ClientException
     * @throws ServerResponseException
     * @throws MissingParameterException
     * @throws NoNodeAvailableException
     */
    public function isCompleted(): bool
    {
        $this->task->refresh();

        return $this->task->isCompleted();
    }
}
