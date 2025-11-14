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

use Psr\Cache\InvalidArgumentException;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\JsonStreamer\Attribute\JsonStreamable;
use Symfony\Component\JsonStreamer\StreamWriterInterface;
use Symfony\Component\TypeInfo\Type;
use Symfony\Contracts\Cache\CacheInterface;

class JsonStreamerAdapter implements DocumentSerializerInterface
{
    private CacheInterface $cache;

    public function __construct(
        private DocumentSerializerInterface $decorated,
        private StreamWriterInterface $streamWriter,
        ?CacheInterface $cache = null,
    ) {
        $this->cache = $cache ?? new ArrayAdapter();
    }

    /**
     * @throws InvalidArgumentException
     */
    public function serialize(object $document): string
    {
        if (!$this->supports($document)) {
            return $this->decorated->serialize($document);
        }

        return (string) $this->streamWriter->write($document, Type::object($document::class));
    }

    /**
     * @throws InvalidArgumentException
     */
    private function supports(object $document): bool
    {
        $key = \sprintf('elastically_supports_%s', md5($document::class));

        return $this->cache->get($key, fn () => \count((new \ReflectionClass($document))->getAttributes(JsonStreamable::class, \ReflectionAttribute::IS_INSTANCEOF)) > 0);
    }
}
