<?php

namespace JoliCode\Elastically;

use Elastica\Index as ElasticaIndex;
use Elastica\ResultSet\BuilderInterface;
use Elastica\Search;

class Index extends ElasticaIndex
{
    private $builder;

    /*
     * Compatibility shortcut, types are no longer needed.
     */
    public function getDocument($id)
    {
        return $this->getType('_doc')->getDocument($id);
    }

    public function getModel($id)
    {
        $document = $this->getDocument($id);

        return $this->getBuilder()->buildModelFromIndexAndData($document->getIndex(), $document->getData());
    }

    public function createSearch($query = '', $options = null, BuilderInterface $builder = null): Search
    {
        $builder = $builder ?? $this->getBuilder();

        return parent::createSearch($query, $options, $builder);
    }

    public function getBuilder(): ResultSetBuilder
    {
        if (!$this->builder) {
            $this->builder = new ResultSetBuilder($this->getClient());
        }

        return $this->builder;
    }
}
