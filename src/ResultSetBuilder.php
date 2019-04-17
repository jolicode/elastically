<?php

namespace JoliCode\Elastically;

use Elastica\Exception\RuntimeException;
use Elastica\Query;
use Elastica\Response;
use Elastica\ResultSet\BuilderInterface;

class ResultSetBuilder implements BuilderInterface
{
    /**
     * @var Client
     */
    private $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function buildResultSet(Response $response, Query $query)
    {
        $results = $this->buildResults($response);
        $resultSet = new ResultSet($response, $query, $results);

        return $resultSet;
    }

    private function buildResults(Response $response)
    {
        $data = $response->getData();
        $results = [];

        if (!isset($data['hits']['hits'])) {
            return $results;
        }

        foreach ($data['hits']['hits'] as $hit) {
            $result = new Result($hit);
            $result->setModel($this->buildModel($result));
            $results[] = $result;
        }

        return $results;
    }

    private function buildModel(Result $result)
    {
        $source = $result->getSource();

        if (empty($source)) {
            return null;
        }

        return $this->buildModelFromIndexAndData($result->getIndex(), $source);
    }

    public function buildModelFromIndexAndData($indexName, $data)
    {
        $pureIndexName = IndexBuilder::getPureIndexName($indexName);
        $indexToClass = $this->client->getConfig(Client::CONFIG_INDEX_CLASS_MAPPING);

        if (!isset($indexToClass[$pureIndexName])) {
            throw new RuntimeException(sprintf('Unknown class for index %s, did you forgot to configure %s?', $pureIndexName, Client::CONFIG_INDEX_CLASS_MAPPING));
        }

        return $this->client->getSerializer()->denormalize($data, $indexToClass[$pureIndexName]);
    }
}
