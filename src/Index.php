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

use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\MissingParameterException;
use Elastic\Elasticsearch\Exception\ServerResponseException;
use Elastic\Transport\Exception\NoNodeAvailableException;
use Elastica\Exception\ExceptionInterface;
use Elastica\Index as ElasticaIndex;
use Elastica\ResultSet\BuilderInterface;
use Elastica\Search;
use Symfony\Component\Serializer\Exception\ExceptionInterface as SerializerExceptionInterface;

class Index extends ElasticaIndex
{
    private ResultSetBuilder $resultSetBuilder;

    public function __construct(Client $client, string $name, ResultSetBuilder $resultSetBuilder)
    {
        parent::__construct($client, $name);

        $this->resultSetBuilder = $resultSetBuilder;
    }

    /**
     * @throws ClientResponseException
     * @throws ExceptionInterface
     * @throws MissingParameterException
     * @throws SerializerExceptionInterface
     * @throws ServerResponseException
     * @throws NoNodeAvailableException
     */
    public function getModel($id): mixed
    {
        $document = $this->getDocument($id);

        return $this->resultSetBuilder->buildModelFromDocument($document);
    }

    public function createSearch($query = '', $options = null, ?BuilderInterface $builder = null): Search
    {
        return parent::createSearch($query, $options, $builder ?? $this->resultSetBuilder);
    }

    public function getClient(): Client
    {
        return parent::getClient();
    }
}
