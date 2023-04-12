<?php

/*
 * This file is part of the jolicode/elastically library.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JoliCode\Elastically\Mapping;

use Elastica\Exception\ExceptionInterface;

interface MappingProviderInterface
{
    /**
     * @throws ExceptionInterface
     */
    public function provideMapping(string $indexName, array $context = []): ?array;
}
