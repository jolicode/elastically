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
        $data = ['actions' => []];

        $data['actions'][] = ['remove' => ['index' => '*', 'alias' => $indexName]];
        $data['actions'][] = ['add' => ['index' => $index->getName(), 'alias' => $indexName]];

        return $this->client->request('_aliases', Request::POST, $data);
    }

    public function slowDownRefresh(Index $index)
    {
        $index->getSettings()->setRefreshInterval('60s');
    }

    public function speedUpRefresh(Index $index)
    {
        $index->getSettings()->setRefreshInterval('1s');
    }

    public static function getPureIndexName($indexName)
    {
        if (1 === preg_match('/(.+)_\d{4}-\d{2}-\d{2}-\d+/i', $indexName, $matches)) {
            return $matches[1];
        }

        return $indexName;
    }

    // TODO
    public function migrate()
    {
    }

    // TODO: WIP + add tests
    public function purgeOldIndices($index)
    {
        //$indexes = $this->client->requestEndpoint(new Get());
        $indexes = $indexes->getData();
        foreach ($indexes as $indexName => &$data) {
            if (0 !== strpos($indexName, $index)) {
                unset($indexes[$indexName]);
                continue;
            }
            $date = \DateTime::createFromFormat('Y-m-d-His', str_replace($index.'_', '', $indexName));
            $data['date'] = $date;
            $data['is_live'] = isset($data['aliases'][$this->getLiveSearchIndexName()]);
        }
        // Newest first
        uasort($indexes, function ($a, $b) {
            return $a['date'] < $b['date'];
        });
        $afterLiveCounter = 0;
        $livePassed = false;
        $deleted = [];
        foreach ($indexes as $indexName => $indexData) {
            if ($livePassed) {
                ++$afterLiveCounter;
            }
            if ($indexData['is_live']) {
                $livePassed = true;
            }
            if ($livePassed && $afterLiveCounter > 2) {
                // Remove!
                $this->client->getIndex($indexName)->delete();
                $deleted[] = $indexName;
            }
        }

        return $deleted;
    }
}
