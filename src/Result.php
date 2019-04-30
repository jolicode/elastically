<?php

namespace JoliCode\Elastically;

use Elastica\Document;
use Elastica\Result as ElasticaResult;

class Result extends ElasticaResult
{
    private $model;

    /**
     * @return object
     */
    public function getModel()
    {
        return $this->model;
    }

    /**
     * @param object $model
     */
    public function setModel($model)
    {
        $this->model = $model;
    }

    public function getDocument(): Document
    {
        $doc = parent::getDocument();
        $doc->setData($this->getModel());

        return $doc;
    }
}
