<?php

/*
 * This file is part of the jolicode/elastically library.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JoliCode\Elastically\Bridge\Symfony\DataCollector;

use Elastica\Document as ElasticaDocument;
use Elastica\Query;
use Elastica\Response;
use Elastica\ResultSet;
use JoliCode\Elastically\Result;
use JoliCode\Elastically\ResultSetBuilder;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Records every model hydrated from Elasticsearch, for the Symfony profiler.
 *
 * @phpstan-type Hydration array{
 *     request: int|null,
 *     index: string|null,
 *     id: string|null,
 *     model: mixed,
 * }
 */
final class TraceableResultSetBuilder extends ResultSetBuilder implements ResetInterface
{
    /** @var list<Hydration> */
    private array $hydrations = [];
    private ?TraceableClient $client = null;

    /**
     * The client is used to link each model to the request it comes from.
     */
    public function setClient(?TraceableClient $client): void
    {
        $this->client = $client;
    }

    public function buildResultSet(Response $response, Query $query): ResultSet
    {
        $resultSet = parent::buildResultSet($response, $query);

        foreach ($resultSet->getResults() as $result) {
            $this->addHydration($result->getIndex(), $result->getId(), $result instanceof Result ? $result->getModel() : null);
        }

        return $resultSet;
    }

    public function buildModelFromIndexAndData(string $indexName, $source)
    {
        $model = parent::buildModelFromIndexAndData($indexName, $source);
        $this->addHydration($indexName, null, $model);

        return $model;
    }

    public function buildModelFromDocument(ElasticaDocument $document)
    {
        $model = parent::buildModelFromDocument($document);
        $this->addHydration($document->getIndex(), $document->getId(), $model);

        return $model;
    }

    /**
     * @return list<Hydration>
     */
    public function getHydrations(): array
    {
        return $this->hydrations;
    }

    public function reset(): void
    {
        $this->hydrations = [];
    }

    private function addHydration(mixed $index, mixed $id, mixed $model): void
    {
        $request = $this->client ? \count($this->client->getTraces()) - 1 : -1;

        $this->hydrations[] = [
            'request' => $request >= 0 ? $request : null,
            'index' => null !== $index ? (string) $index : null,
            'id' => null !== $id ? (string) $id : null,
            'model' => $model,
        ];
    }
}
