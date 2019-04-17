<?php
namespace JoliCode\Elastically;

use Elastica\Document;
use Elastica\Result as ElasticaResult;

class Result extends ElasticaResult
{
    protected $model;

    /**
     * @return \stdClass
     */
    public function getModel()
    {
        return $this->model;
    }

    /**
     * @param \stdClass $model
     */
    public function setModel($model)
    {
        $this->model = $model;
    }

    /**
     * Returns Document.
     *
     * @return Document
     */
    public function getDocument()
    {
        $doc = parent::getDocument();
        $doc->setData($this->getModel());

        return $doc;
    }
}