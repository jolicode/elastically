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

use Elastica\Document;
use Elastica\Query;
use Elastica\Response;
use Elastica\ResultSet;
use Elastica\ResultSet\BuilderInterface;

class ResultSetBuilder implements BuilderInterface
{
    public const RESULT_KEY = 'elastically_result';
    public const DOCUMENT_KEY = 'elastically_document';

    private Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function buildResultSet(Response $response, Query $query): ResultSet
    {
        $data = $response->getData();
        $results = [];

        if (!isset($data['hits']['hits'])) {
            return new ResultSet($response, $query, []);
        }

        foreach ($data['hits']['hits'] as $hit) {
            $result = new Result($hit);
            $result->setModel($this->buildModelFromResult($result));
            $results[] = $result;
        }

        return new ResultSet($response, $query, $results);
    }

    public function buildModelFromIndexAndData(string $indexName, $source)
    {
        return $this->buildModel($indexName, $source, []);
    }

    public function buildModelFromDocument(Document $document)
    {
        return $this->buildModel($document->getData(), $document->getIndex(), [
            self::DOCUMENT_KEY => $document,
        ]);
    }

    private function buildModelFromResult(Result $result)
    {
        return $this->buildModel($result->getSource(), $result->getIndex(), [
            self::RESULT_KEY => $result,
        ]);
    }

    private function buildModel($source, string $indexName, array $context)
    {
        if (!$source) {
            return null;
        }

        $class = $this->client->getClassFromIndexName($this->client->getPureIndexName($indexName));

        $context = array_merge($this->client->getSerializerContext($class), $context);

        return $this->client->getSerializer()->denormalize($source, $class, null, $context);
    }
}
