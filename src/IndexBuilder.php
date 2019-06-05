<?php

namespace JoliCode\Elastically;

use Elastica\Exception\InvalidException;
use Elastica\Exception\RuntimeException;
use Elastica\Index;
use Elastica\Request;
use Elastica\Response;
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

    public function createIndex($indexName): Index
    {
        $mappingFilePath = $this->configurationDirectory.DIRECTORY_SEPARATOR.$indexName.'_mapping.yaml';
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
        $indexName = $this->client->getConfigValue(Client::CONFIG_INDEX_PREFIX).$indexName;

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

    public function getPureIndexName($indexName): string
    {
        $prefix = $this->client->getConfigValue(Client::CONFIG_INDEX_PREFIX);
        $pattern = sprintf('/%s(.+)_\d{4}-\d{2}-\d{2}-\d+/i', preg_quote($prefix, '/'));
        if (1 === preg_match($pattern, $indexName, $matches)) {
            return $matches[1];
        }

        return $indexName;
    }

    public function migrate(Index $current, Index $new)
    {
        // @todo Waiting for https://github.com/ruflin/Elastica/pull/1637 to be merged
        // This method should use the TASK API, because we do not want to WAIT for the reindex (HTTP Timeout issues).
    }

    public function purgeOldIndices($indexName): array
    {
        $indexName = $this->client->getConfigValue(Client::CONFIG_INDEX_PREFIX).$indexName;

        $aliases = $this->client->requestEndpoint(new \Elasticsearch\Endpoints\Indices\Alias\Get());

        $indexes = $aliases->getData();

        foreach ($indexes as $realIndexName => &$data) {
            if (0 !== strpos($realIndexName, $indexName)) {
                unset($indexes[$realIndexName]);
                continue;
            }

            $date = \DateTime::createFromFormat('Y-m-d-His', str_replace($indexName.'_', '', $realIndexName));
            $data['date'] = $date;
            $data['is_live'] = isset($data['aliases'][$indexName]);
        }

        // Newest first
        uasort($indexes, function ($a, $b) {
            return $a['date'] < $b['date'];
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
                $this->client->getIndex($realIndexName)->delete();
                $operations[] = sprintf('%s deleted.', $realIndexName);
            } elseif ($livePassed && 1 === $afterLiveCounter) {
                // Close
                $this->client->getIndex($realIndexName)->close();
                $operations[] = sprintf('%s closed.', $realIndexName);
            }
        }

        return $operations;
    }
}
