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

use Elastica\Index as ElasticaIndex;
use Elastica\ResultSet\BuilderInterface;
use Elastica\Search;

class Index extends ElasticaIndex
{
    public function getModel($id)
    {
        $document = $this->getDocument($id);

        return $this->getClient()->getBuilder()->buildModelFromDocument($document);
    }

    public function createSearch($query = '', $options = null, BuilderInterface $builder = null): Search
    {
        return parent::createSearch($query, $options, $builder ?? $this->getClient()->getBuilder());
    }

    public function getBuilder(): ResultSetBuilder
    {
        trigger_deprecation('jolicode/elastically', '1.3.0', 'Method %s() is deprecated. Use %s::getBuilder() instead', __METHOD__, Client::class);

        return $this->getClient()->getBuilder();
    }

    public function getClient(): Client
    {
        return parent::getClient();
    }
}
