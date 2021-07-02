<?php

namespace JoliCode\Elastically;

use Elastica\Bulk\Action;
use Elastica\Bulk as ElasticaBulk;

class Bulk extends ElasticaBulk
{
    protected $size = 0;

    public function getSize()
    {
        return $this->size;
    }

    public function addAction(Action $action): self
    {
        parent::addAction($action);
        ++$this->size;

        return $this;
    }
}
