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

use Elastica\Result as ElasticaResult;
use JoliCode\Elastically\Model\Document;

class Result extends ElasticaResult
{
    private object $model;

    public function getModel(): object
    {
        return $this->model;
    }

    public function setModel(object $model)
    {
        $this->model = $model;
    }

    public function getDocument(): Document
    {
        return Document::createFromDocument(parent::getDocument(), $this->model);
    }
}
