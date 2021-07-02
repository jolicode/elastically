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

use Elastica\Query;
use Elastica\Response;
use Elastica\ResultSet;
use Elastica\ResultSet\BuilderInterface;

class ResultSetBuilder implements BuilderInterface
{
    public const RESULT_KEY = 'elastically_result';

    private $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function buildResultSet(Response $response, Query $query): ResultSet
    {
        $data = $response->getData();
        $results = [];

        if (!isset($data['hits']['hits'])) {
            return $results;
        }

        foreach ($data['hits']['hits'] as $hit) {
            $result = new Result($hit);
            $result->setModel($this->buildModelResult($result));
            $results[] = $result;
        }

        return new ResultSet($response, $query, $results);
    }

    public function buildModelFromIndexAndData(string $indexName, $data)
    {
        $class = $this->client->getClassFromIndexName($this->client->getPureIndexName($indexName));

        $context = $this->client->getSerializerContext($class);

        return $this->client->getSerializer()->denormalize($data, $class, null, $context);
    }

    private function buildModelResult(Result $result)
    {
        $source = $result->getSource();
        if (!$source) {
            return null;
        }

        $class = $this->client->getClassFromIndexName($this->client->getPureIndexName($result->getIndex()));

        $context = $this->client->getSerializerContext($class);
        $context[self::RESULT_KEY] = $result;

        return $this->client->getSerializer()->denormalize($source, $class, null, $context);
    }
}
