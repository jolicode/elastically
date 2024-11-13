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

use Elastica\Client as ElasticaClient;
use Psr\Log\LoggerInterface;

class Client extends ElasticaClient
{
    private ResultSetBuilder $resultSetBuilder;
    private IndexNameMapper $indexNameMapper;

    /**
     * @see \JoliCode\Elastically\Factory::buildClient
     */
    public function __construct($config = [], ?LoggerInterface $logger = null, ?ResultSetBuilder $resultSetBuilder = null, ?IndexNameMapper $indexNameMapper = null)
    {
        parent::__construct($config, $logger);

        if (!$resultSetBuilder || !$indexNameMapper) {
            throw new \InvalidArgumentException('Missing argument "resultSetBuilder" and "indexNameMapper", use `\JoliCode\Elastically\Factory::buildClient` to create this Client.');
        }

        $this->resultSetBuilder = $resultSetBuilder;
        $this->indexNameMapper = $indexNameMapper;
    }

    /**
     * Return an Elastically index.
     *
     * @return Index
     */
    public function getIndex(string $name): \Elastica\Index
    {
        $name = $this->indexNameMapper->getPrefixedIndex($name);

        return new Index($this, $name, $this->resultSetBuilder);
    }
}
