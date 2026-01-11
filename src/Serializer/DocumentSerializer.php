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
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\TypeInfo\Type;
use Symfony\Contracts\Cache\CacheInterface;

class DocumentSerializer implements SerializerInterface
{
    private ContextBuilderInterface $contextBuilder;
    private CacheInterface $cache;
    private ?StreamWriterInterface $streamWriter;

    public function __construct(
        private SerializerInterface $serializer,
        ?ContextBuilderInterface $contextBuilder = null,
        ?StreamWriterInterface $streamWriter = null,
        ?CacheInterface $cache = null,
    ) {
        $this->contextBuilder = $contextBuilder ?? new StaticContextBuilder();
        $this->streamWriter = $streamWriter;
        $this->cache = $cache ?? new ArrayAdapter();
    }

    /**
     * @throws ExceptionInterface|InvalidArgumentException
     */
    public function serialize(mixed $data, string $format, array $context = []): string
    {
        // Handle non-object data or when format is not JSON
        if (!\is_object($data) || 'json' !== $format) {
            return $this->serializer->serialize($data, $format, $context);
        }

        // Check if document supports JsonStreamable
        if ($this->supportsJsonStreaming($data)) {
            return (string) $this->streamWriter->write($data, Type::object($data::class));
        }

        $context = $this->contextBuilder->buildContext($data::class);

        return $this->serializer->serialize($data, $format, $context);
    }

    /**
     * @throws ExceptionInterface
     */
    public function deserialize(mixed $data, string $type, string $format, array $context = []): mixed
    {
        return $this->serializer->deserialize($data, $type, $format, $context);
    }

    /**
     * @throws InvalidArgumentException
     */
    private function supportsJsonStreaming(object $document): bool
    {
        // JsonStreamer not available
        if (!$this->streamWriter instanceof StreamWriterInterface) {
            return false;
        }

        $key = \sprintf('elastically_supports_%s', hash('xxh3', $document::class));

        return $this->cache->get($key, fn () => \count((new \ReflectionClass($document))->getAttributes(JsonStreamable::class, \ReflectionAttribute::IS_INSTANCEOF)) > 0);
    }
}
