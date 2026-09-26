<?php

/*
 * This file is part of the jolicode/elastically library.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JoliCode\Elastically\Messenger;

use Elastica\Document as ElasticaDocument;

/**
 * Implement this interface to fetch all the documents of a MultipleIndexationRequest
 * at once (one query per class) instead of one by one.
 */
interface MultipleDocumentExchangerInterface extends DocumentExchangerInterface
{
    /**
     * @param list<string> $ids
     *
     * @return iterable<string, ElasticaDocument|null> Documents indexed by their ID. A missing (or null) ID means the document does not exist anymore and will be deleted.
     */
    public function fetchDocuments(string $className, array $ids): iterable;
}
