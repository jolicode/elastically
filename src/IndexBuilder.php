<?php

namespace JoliCode\Elastically;

use Elastica\Exception\InvalidException;
use Elastica\Exception\RuntimeException;
use Elastica\Reindex;
use Elastica\Request;
use Elastica\Response;
use Elastica\Task;
use Elasticsearch\Endpoints\Cluster\State;
use Symfony\Component\Yaml\Yaml;

class IndexBuilder
{
    private $client;
    private $configurationDirectory;

    public function __construct(Client $client, $configurationDirectory)
    {
        $this->client = $client;
        $this->configurationDirectory = $configurationDirectory;
    }

    public function createIndex($indexName, $fileName = null): Index
    {
        $fileName = $fileName ?? ($indexName.'_mapping.yaml');
        $mappingFilePath = $this->configurationDirectory.DIRECTORY_SEPARATOR.$fileName;
        if (!is_file($mappingFilePath)) {
            throw new InvalidException(sprintf('Mapping file "%s" not found.', $mappingFilePath));
        }
        $mapping = Yaml::parseFile($mappingFilePath);

        $analyzerFilePath = $this->configurationDirectory.'/analyzers.yaml';
        if ($mapping && is_file($analyzerFilePath)) {
            $analyzer = Yaml::parseFile($analyzerFilePath);
            $mapping['settings']['analysis'] = array_merge_recursive($mapping['settings']['analysis'] ?? [], $analyzer);
        }

        $realName = sprintf('%s_%s', $indexName, date('Y-m-d-His'));
        $index = $this->client->getIndex($realName);

        if ($index->exists()) {
            throw new RuntimeException(sprintf('Index "%s" is already created, something is wrong.', $index->getName()));
        }

        $index->create($mapping ?? []);

        return $index;
    }

    public function markAsLive(Index $index, $indexName): Response
    {
        $indexName = $this->client->getPrefixedIndex($indexName);

        $data = ['actions' => []];

        $data['actions'][] = ['remove' => ['index' => '*', 'alias' => $indexName]];
        $data['actions'][] = ['add' => ['index' => $index->getName(), 'alias' => $indexName]];

        return $this->client->request('_aliases', Request::POST, $data);
    }

    public function slowDownRefresh(Index $index): void
    {
        $index->getSettings()->setRefreshInterval('60s');
    }

    public function speedUpRefresh(Index $index): void
    {
        $index->getSettings()->setRefreshInterval('1s');
    }

    public function migrate(Index $currentIndex, array $params = [])
    {
        $pureIndexName = $this->client->getPureIndexName($currentIndex->getName());
        $newIndex = $this->createIndex($pureIndexName);

        $reindex = new Reindex($currentIndex, $newIndex, $params);
        $reindex->setWaitForCompletion(Reindex::WAIT_FOR_COMPLETION_FALSE);

        $response = $reindex->run();

        if ($response->isOk()) {
            $taskId = $response->getData()['task'];

            $task = new Task($this->client, $taskId);

            while (false === $task->isCompleted()) {
                sleep(1); // Migrate of an index is not a production critical operation, sleep is ok.
                $task->refresh();
            }

            return $newIndex;
        } else {
            throw new RuntimeException(sprintf('Reindex call failed. %s', $response->getError()));
        }
    }

    public function purgeOldIndices(string $indexName): array
    {
        $indexName = $this->client->getPrefixedIndex($indexName);

        $stateRequest = new State();
        $stateRequest->setParams([
            'filter_path' => 'metadata.indices.*.state,metadata.indices.*.aliases',
        ]);

        $indexes = $this->client->requestEndpoint($stateRequest);
        $indexes = $indexes->getData();
        $indexes = $indexes['metadata']['indices'];

        foreach ($indexes as $realIndexName => &$data) {
            if (0 !== strpos($realIndexName, $indexName)) {
                unset($indexes[$realIndexName]);
                continue;
            }

            // Check suffix (it must contains a valid date)
            $indexSuffixName = substr($realIndexName, strlen($indexName) + 1);
            $date = \DateTime::createFromFormat('Y-m-d-His', $indexSuffixName);
            if (!$date) {
                unset($indexes[$realIndexName]);
                continue;
            }

            $data['date'] = $date;
            $data['is_live'] = false !== array_search($indexName, $data['aliases']);
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
                $index = new Index($this->client, $realIndexName);
                $index->delete();
                $operations[] = sprintf('%s deleted.', $realIndexName);
            } elseif ($livePassed && 1 === $afterLiveCounter) {
                // Close
                $index = new Index($this->client, $realIndexName);
                $index->close();
                $operations[] = sprintf('%s closed.', $realIndexName);
            }
        }

        return $operations;
    }
}
