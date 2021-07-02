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
