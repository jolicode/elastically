<?php

/*
 * This file is part of the jolicode/elastically library.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JoliCode\Elastically\Serializer;

final class StaticContextBuilder implements ContextBuilderInterface
{
    private array $mapping;

    public function __construct(array $mapping = [])
    {
        $this->mapping = $mapping;
    }

    public function buildContext(string $class): array
    {
        return $this->mapping[$class] ?? [];
    }
}
