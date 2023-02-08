<?php

/*
 * This file is part of the jolicode/elastically library.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JoliCode\Elastically\Model;

use Elastica\Document as ElasticaDocument;
use Elastica\Index;

class Document extends ElasticaDocument
{
    private ?object $model;

    public function __construct(?string $id, ?object $model = null, string|array $data = [], Index|string $index = '')
    {
        parent::__construct($id, $data, $index);

        $this->model = $model;
    }

    public static function createFromDocument(ElasticaDocument $document, ?object $model = null): self
    {
        return new self($document->getId(), $model, $document->getData(), $document->getIndex());
    }

    public function getModel(): ?object
    {
        return $this->model;
    }
}
