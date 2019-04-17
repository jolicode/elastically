<?php

namespace JoliCode\Elastically;

use Elastica\Query;
use Elastica\Response;
use Elastica\ResultSet;
use Elastica\ResultSet\BuilderInterface;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;

class ResultSetBuilder implements BuilderInterface
{
    /**
     * @var Serializer
     */
    private $serializer;

    public function __construct(SerializerInterface $serializer)
    {
        $this->serializer = $serializer;
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

        return $this->serializer->denormalize($source, \Product::class);
    }
}