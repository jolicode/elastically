<?php

namespace JoliCode\Elastically;

use Elastica\Client;
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
        $mapping = Yaml::parse(file_get_contents($this->configurationDirectory. DIRECTORY_SEPARATOR . $indexName .'_mapping.yaml'));
        $analyzer = Yaml::parse(file_get_contents($this->configurationDirectory. '/analyzers.yaml'));

        $mapping['settings']['analysis'] = array_merge_recursive($mapping['settings']['analysis'] ?? [], $analyzer);

        $realName = sprintf('%s_%s', $indexName, date('Y-m-d-His'));
        $index = $this->client->getIndex($realName);

        if ($index->exists()) {
            throw new \RuntimeException(sprintf('Index %s is already created, something is wrong.', $index->getName()));
        }

        $index->create($mapping);

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

    public function refresh($index)
    {
        $this->client->getIndex($index)->refresh();
    }

    public function migrate()
    {
        // todo
    }
}