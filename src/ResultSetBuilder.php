<?php

namespace JoliCode\Elastically;

use Elastica\Exception\RuntimeException;
use Elastica\Query;
use Elastica\Response;
use Elastica\ResultSet;
use Elastica\ResultSet\BuilderInterface;

class ResultSetBuilder implements BuilderInterface
{
    private $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function buildResultSet(Response $response, Query $query): ResultSet
    {
        $results = $this->buildResults($response);

        return new ResultSet($response, $query, $results);
    }

    private function buildResults(Response $response): array
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
        $pureIndexName = $this->client->getPureIndexName($indexName);
        $indexToClass = $this->client->getConfig(Client::CONFIG_INDEX_CLASS_MAPPING);
        if (!isset($indexToClass[$pureIndexName])) {
            throw new RuntimeException(sprintf('Unknown class for index "%s", did you forgot to configure "%s"?', $pureIndexName, Client::CONFIG_INDEX_CLASS_MAPPING));
        }

        $context = $this->client->getSerializerContext($indexToClass[$pureIndexName]);

        return $this->client->getSerializer()->denormalize($data, $indexToClass[$pureIndexName], null, $context);
    }
}
