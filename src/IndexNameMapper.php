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
use Elastica\Exception\RuntimeException;

class IndexNameMapper
{
    private ?string $prefix;
    private array $indexClassMapping;

    public function __construct(?string $prefix, array $indexClassMapping)
    {
        $this->prefix = $prefix;
        $this->indexClassMapping = $indexClassMapping;
    }

    public function getPrefixedIndex(string $name): string
    {
        if ($this->prefix) {
            return \sprintf('%s_%s', $this->prefix, $name);
        }

        return $name;
    }

    /**
     * @throws ExceptionInterface
     */
    public function getIndexNameFromClass(string $className): string
    {
        $indexName = array_search($className, $this->indexClassMapping, true);

        if (!$indexName) {
            throw new RuntimeException(\sprintf('The given type (%s) does not exist in the configuration.', $className));
        }

        return $this->getPrefixedIndex($indexName);
    }

    /**
     * @throws ExceptionInterface
     */
    public function getClassFromIndexName(string $indexName): string
    {
        if (!isset($this->indexClassMapping[$indexName])) {
            throw new RuntimeException(\sprintf('Unknown class for index "%s". Please check your configuration.', $indexName));
        }

        return $this->indexClassMapping[$indexName];
    }

    public function getPureIndexName(string $fullIndexName): string
    {
        if ($this->prefix) {
            $pattern = \sprintf('/%s_(.+)_\d{4}-\d{2}-\d{2}-\d+/i', preg_quote($this->prefix, '/'));
        } else {
            $pattern = '/(.+)_\d{4}-\d{2}-\d{2}-\d+/i';
        }

        if (1 === preg_match($pattern, $fullIndexName, $matches)) {
            return $matches[1];
        }

        $prefixLength = $this->prefix ? \strlen($this->prefix) : 0;

        if ($this->prefix && substr($fullIndexName, 0, $prefixLength) === $this->prefix) {
            return substr($fullIndexName, $prefixLength + 1);
        }

        return $fullIndexName;
    }

    public function getMappedIndices(): array
    {
        return array_keys($this->indexClassMapping);
    }
}
