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
use Elastica\Exception\ExceptionInterface;
use Elastica\Exception\RuntimeException;
use Elastica\Query;
use Elastica\Response;
use Elastica\ResultSet;
use Elastica\ResultSet\BuilderInterface;
use JoliCode\Elastically\Serializer\ContextBuilderInterface;
use Symfony\Component\Serializer\Exception\ExceptionInterface as SerializerExceptionInterface;
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

    /**
     * @throws ExceptionInterface
     * @throws SerializerExceptionInterface
     */
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

    /**
     * @throws ExceptionInterface
     * @throws SerializerExceptionInterface
     */
    public function buildModelFromIndexAndData(string $indexName, $source, $fields)
    {
        return $this->buildModel($source, $fields, $indexName, []);
    }

    /**
     * @throws ExceptionInterface
     * @throws SerializerExceptionInterface
     */
    public function buildModelFromDocument(ElasticaDocument $document)
    {
        return $this->buildModel($document->getData(), $document->hasFields() ? $document->getFields() : [], $document->getIndex(), [
            self::DOCUMENT_KEY => $document,
        ]);
    }

    /**
     * @throws ExceptionInterface
     * @throws SerializerExceptionInterface
     */
    private function buildModelFromResult(Result $result)
    {
        if (!$result->getIndex()) {
            throw new RuntimeException('Returned index is empty. Check your "filter_path".');
        }

        return $this->buildModel($result->getSource(), $result->hasFields() ? $result->getFields() : [], $result->getIndex(), [
            self::RESULT_KEY => $result,
        ]);
    }

    /**
     * @throws ExceptionInterface
     * @throws SerializerExceptionInterface
     */
    private function buildModel($source, $fields, string $indexName, array $context)
    {
        if (!$source && !$fields) {
            return null;
        }

        $class = $this->indexNameMapper->getClassFromIndexName($this->indexNameMapper->getPureIndexName($indexName));

        $context = array_merge($this->contextBuilder->buildContext($class), $context);
        $fieldsFlat = array_map(fn ($field) => reset($field), $fields);

        return $this->denormalizer->denormalize(array_merge($source, $fieldsFlat), $class, null, $context);
    }
}
