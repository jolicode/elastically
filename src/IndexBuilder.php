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
use Elastica\Exception\ExceptionInterface;
use Elastica\Exception\RuntimeException;
use Elastica\Reindex;
use Elastica\Response;
use Elastica\Task;
use JoliCode\Elastically\Mapping\MappingProviderInterface;

class IndexBuilder
{
    private MappingProviderInterface $mappingProvider;
    private Client $client;
    private IndexNameMapper $indexNameMapper;

    public function __construct(MappingProviderInterface $mappingProvider, Client $client, IndexNameMapper $indexNameMapper)
    {
        $this->mappingProvider = $mappingProvider;
        $this->client = $client;
        $this->indexNameMapper = $indexNameMapper;
    }

    /**
     * @throws ExceptionInterface
     * @throws ClientResponseException
     * @throws ServerResponseException
     * @throws MissingParameterException
     * @throws NoNodeAvailableException
     */
    public function createIndex(string $indexName, array $context = []): Index
    {
        $mapping = $this->mappingProvider->provideMapping($indexName, $context);

        $realName = \sprintf('%s_%s', $indexName, date('Y-m-d-His'));
        $index = $this->client->getIndex($realName);

        if ($index->exists()) {
            throw new RuntimeException(\sprintf('Index "%s" is already created, something is wrong.', $index->getName()));
        }

        $index->create($mapping ?? []);

        return $index;
    }

    /**
     * @throws ClientResponseException
     * @throws ServerResponseException
     * @throws NoNodeAvailableException
     */
    public function markAsLive(Index $index, string $indexName): Response
    {
        $indexPrefixedName = $this->indexNameMapper->getPrefixedIndex($indexName);

        $data = ['actions' => []];

        $data['actions'][] = ['remove' => ['index' => $indexPrefixedName . '*', 'alias' => $indexPrefixedName]];
        $data['actions'][] = ['add' => ['index' => $index->getName(), 'alias' => $indexPrefixedName]];

        return $this->client->toElasticaResponse(
            $this->client->indices()->updateAliases(['index' => $indexName, 'body' => $data])
        );
    }

    /**
     * @throws ClientResponseException
     * @throws ClientException
     * @throws ServerResponseException
     * @throws NoNodeAvailableException
     */
    public function slowDownRefresh(Index $index): void
    {
        $index->getSettings()->setRefreshInterval('60s');
    }

    /**
     * @throws ClientResponseException
     * @throws ClientException
     * @throws ServerResponseException
     * @throws NoNodeAvailableException
     */
    public function speedUpRefresh(Index $index): void
    {
        $index->getSettings()->setRefreshInterval('1s');
    }

    /**
     * @throws ExceptionInterface
     * @throws ClientResponseException
     * @throws ServerResponseException
     * @throws MissingParameterException
     * @throws NoNodeAvailableException
     */
    public function migrate(Index $currentIndex, array $params = [], array $context = []): Index
    {
        $pureIndexName = $this->indexNameMapper->getPureIndexName($currentIndex->getName());
        $newIndex = $this->createIndex($pureIndexName, $context);

        $reindex = new Reindex($currentIndex, $newIndex, $params);
        $reindex->setWaitForCompletion(false);

        $response = $reindex->run();

        if (!$response->isOk()) {
            throw new RuntimeException(\sprintf('Reindex call failed. %s', $response->getError()));
        }

        $taskId = $response->getData()['task'];

        $task = new Task($this->client, $taskId);

        while (false === $task->isCompleted()) {
            sleep(1); // Migrate of an index is not a production critical operation, sleep is ok.
            $task->refresh();
        }

        return $newIndex;
    }

    /**
     * @throws ClientResponseException
     * @throws ClientException
     * @throws ServerResponseException
     * @throws MissingParameterException
     * @throws NoNodeAvailableException
     */
    public function purgeOldIndices(string $indexName, bool $dryRun = false): array
    {
        $indexName = $this->indexNameMapper->getPrefixedIndex($indexName);

        $state = $this->client->cluster()->state([
            'filter_path' => 'metadata.indices.*.state,metadata.indices.*.aliases',
        ]);

        $indexes = $this->client->toElasticaResponse($state)->getData();
        $indexes = $indexes['metadata']['indices'];

        foreach ($indexes as $realIndexName => &$data) {
            if (!str_starts_with($realIndexName, $indexName)) {
                unset($indexes[$realIndexName]);

                continue;
            }

            // Check suffix (it must contain a valid date)
            $indexSuffixName = substr($realIndexName, \strlen($indexName) + 1);
            try {
                $date = \DateTime::createFromFormat('Y-m-d-His', $indexSuffixName);
            } catch (\ValueError) {
                $date = null;
            }

            if (!$date) {
                unset($indexes[$realIndexName]);

                continue;
            }

            $data['date'] = $date;
            $data['is_live'] = \in_array($indexName, $data['aliases'], true);
        }

        // Newest first
        uasort($indexes, function ($a, $b) {
            return $b['date'] <=> $a['date'];
        });

        $afterLiveCounter = 0;
        $livePassed = false;
        $operations = [];

        foreach ($indexes as $realIndexName => $indexData) {
            if ($livePassed) {
                ++$afterLiveCounter;
            }

            if ($indexData['is_live']) {
                $livePassed = true;
            }

            if ($livePassed && $afterLiveCounter > 1) {
                // Remove
                if (false === $dryRun) {
                    $index = new \Elastica\Index($this->client, $realIndexName);
                    $index->delete();
                }
                $operations[] = \sprintf('%s deleted.', $realIndexName);
            } elseif ($livePassed && 1 === $afterLiveCounter) {
                // Close
                if (false === $dryRun) {
                    $index = new \Elastica\Index($this->client, $realIndexName);
                    $index->close();
                }
                $operations[] = \sprintf('%s closed.', $realIndexName);
            }
        }

        return $operations;
    }
}
