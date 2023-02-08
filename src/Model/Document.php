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

class Document extends ElasticaDocument
{
    private ?object $dto;

    public function __construct($id, object $dto = null, $data = [], $index = '')
    {
        parent::__construct($id, $data, $index);

        $this->dto = $dto;
    }

    public static function createFromDocument(ElasticaDocument $document, object $dto = null): self
    {
        return new self($document->getId(), $dto, $document->getData(), $document->getIndex());
    }

    public function getDto(): object
    {
        return $this->dto;
    }
}
