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

use Elastica\Document as ElasticaDocument;
use Elastica\Exception\RuntimeException;
use Elastica\Query;
use Elastica\Response;
use Elastica\ResultSet;
use Elastica\ResultSet\BuilderInterface;
use JoliCode\Elastically\Model\Document;
use JoliCode\Elastically\Serializer\ContextBuilderInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class ResultSetBuilder implements BuilderInterface
{
    public const RESULT_KEY = 'elastically_result';
    public const DOCUMENT_KEY = 'elastically_document';

    private IndexNameMapper $indexNameMapper;
    private ContextBuilderInterface $contextBuilder;
    private DenormalizerInterface $denormalizer;

    public function __construct(IndexNameMapper $indexNameMapper, ContextBuilderInterface $contextBuilder, DenormalizerInterface $denormalizer)
    {
        $this->indexNameMapper = $indexNameMapper;
        $this->contextBuilder = $contextBuilder;
        $this->denormalizer = $denormalizer;
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

    public function buildModelFromDocument(Document|ElasticaDocument $document)
    {
        $data = match(true) {
            $document instanceof Document => $document->getDto(),
            $document instanceof ElasticaDocument => $document->getData(),
        };

        return $this->buildModel($data, $document->getIndex(), [
            self::DOCUMENT_KEY => $document,
        ]);
    }

    private function buildModelFromResult(Result $result)
    {
        if (!$result->getIndex()) {
            throw new RuntimeException('Returned index is empty. Check your "filter_path".');
        }

        return $this->buildModel($result->getSource(), $result->getIndex(), [
            self::RESULT_KEY => $result,
        ]);
    }

    private function buildModel($source, string $indexName, array $context)
    {
        if (!$source) {
            return null;
        }

        $class = $this->indexNameMapper->getClassFromIndexName($this->indexNameMapper->getPureIndexName($indexName));

        $context = array_merge($this->contextBuilder->buildContext($class), $context);

        return $this->denormalizer->denormalize($source, $class, null, $context);
    }
}
