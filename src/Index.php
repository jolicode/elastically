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

use Elastica\Exception\ExceptionInterface;
use Elastica\Index as ElasticaIndex;
use Elastica\ResultSet\BuilderInterface;
use Elastica\Search;

class Index extends ElasticaIndex
{
    private ResultSetBuilder $resultSetBuilder;

    public function __construct(Client $client, string $name, ResultSetBuilder $resultSetBuilder)
    {
        parent::__construct($client, $name);

        $this->resultSetBuilder = $resultSetBuilder;
    }

    /**
     * @throws ExceptionInterface
     */
    public function getModel($id)
    {
        $document = $this->getDocument($id);

        return $this->resultSetBuilder->buildModelFromDocument($document);
    }

    public function createSearch($query = '', $options = null, BuilderInterface $builder = null): Search
    {
        return parent::createSearch($query, $options, $builder ?? $this->resultSetBuilder);
    }

    public function getBuilder(): ResultSetBuilder
    {
        trigger_deprecation('jolicode/elastically', '1.3.0', 'Method %s() is deprecated. Use %s::getBuilder() instead', __METHOD__, Client::class);

        return $this->resultSetBuilder;
    }

    public function getClient(): Client
    {
        return parent::getClient();
    }
}
