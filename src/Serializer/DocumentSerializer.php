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

use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\SerializerInterface;

class DocumentSerializer implements DocumentSerializerInterface
{
    private ContextBuilderInterface $contextBuilder;

    public function __construct(private SerializerInterface $serializer, ?ContextBuilderInterface $contextBuilder = null)
    {
        $this->contextBuilder = $contextBuilder ?? new StaticContextBuilder();
    }

    /**
     * @throws ExceptionInterface
     */
    public function serialize(object $document, array $context = []): string
    {
        $context = $this->contextBuilder->buildContext($document::class);

        return $this->serializer->serialize($document, 'json', $context);
    }
}
