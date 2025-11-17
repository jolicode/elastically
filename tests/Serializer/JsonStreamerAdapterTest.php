<?php

declare(strict_types=1);

/*
 * This file is part of the jolicode/elastically library.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JoliCode\Elastically\Tests\Serializer;

use JoliCode\Elastically\Serializer\DocumentSerializerInterface;
use JoliCode\Elastically\Serializer\JsonStreamerAdapter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\JsonStreamer\Attribute\JsonStreamable;
use Symfony\Component\JsonStreamer\StreamWriterInterface;
use Symfony\Component\TypeInfo\Type;
use Symfony\Contracts\Cache\CacheInterface;

final class JsonStreamerAdapterTest extends TestCase
{
    private DocumentSerializerInterface $decoratedSerializer;
    private StreamWriterInterface $streamWriter;
    private ArrayAdapter $cache;
    private JsonStreamerAdapter $adapter;

    protected function setUp(): void
    {
        if (!class_exists(StreamWriterInterface::class)) {
            $this->markTestSkipped('Skipping as JsonStreamer is not installed.');
        }

        $this->decoratedSerializer = $this->createMock(DocumentSerializerInterface::class);
        $this->streamWriter = $this->createMock(StreamWriterInterface::class);
        $this->cache = new ArrayAdapter();
        $this->adapter = new JsonStreamerAdapter(
            $this->decoratedSerializer,
            $this->streamWriter,
            $this->cache
        );
    }

    public function testSerializeWithoutJsonStreamableAttribute(): void
    {
        $document = new class {
            public string $name = 'test';
        };

        $expectedSerialized = '{"name":"test"}';
        $this->decoratedSerializer
            ->expects($this->once())
            ->method('serialize')
            ->with($document)
            ->willReturn($expectedSerialized)
        ;

        $this->streamWriter->expects($this->never())->method('write');

        $result = $this->adapter->serialize($document);

        $this->assertSame($expectedSerialized, $result);
    }

    public function testSerializeWithJsonStreamableAttribute(): void
    {
        $document = new JsonStreamableTestDocument();

        $expectedSerialized = '{"items":[1,2,3]}';
        $stringableResult = new StringableTraversable($expectedSerialized);
        $this->streamWriter
            ->expects($this->once())
            ->method('write')
            ->with(
                $document,
                $this->callback(fn ($type) => $type instanceof Type && JsonStreamableTestDocument::class === $type->getClassName())
            )
            ->willReturn($stringableResult)
        ;

        $this->decoratedSerializer->expects($this->never())->method('serialize');

        $result = $this->adapter->serialize($document);

        $this->assertSame($expectedSerialized, $result);
    }

    public function testSerializeConvertsStreamWriterResultToString(): void
    {
        $document = new JsonStreamableTestDocument();

        $streamWriterResult = new StringableTraversable('{"items":[1,2,3]}');

        $this->streamWriter
            ->expects($this->once())
            ->method('write')
            ->willReturn($streamWriterResult)
        ;

        $result = $this->adapter->serialize($document);

        $this->assertSame('{"items":[1,2,3]}', $result);
        $this->assertIsString($result);
    }

    public function testSupportsIsCached(): void
    {
        $document1 = new JsonStreamableTestDocument();
        $document2 = new JsonStreamableTestDocument();

        $reflectionCallCount = 0;
        $cacheCallCount = 0;

        // Create a mock cache to track calls
        $cache = $this->createMock(CacheInterface::class);
        $cache
            ->expects($this->exactly(2))
            ->method('get')
            ->willReturnCallback(function ($key, $callback) use (&$cacheCallCount, &$reflectionCallCount) {
                ++$cacheCallCount;
                // Only increment reflection count on second call if cache returns cached value
                if (2 === $cacheCallCount) {
                    // Cache hit - the callback won't call reflection again
                    return true;
                }
                // First call - callback is executed
                ++$reflectionCallCount;

                return $callback();
            })
        ;

        $adapter = new JsonStreamerAdapter(
            $this->decoratedSerializer,
            $this->streamWriter,
            $cache
        );

        $this->streamWriter
            ->expects($this->exactly(2))
            ->method('write')
            ->willReturn(new StringableTraversable('{"items":[]}'))
        ;

        $adapter->serialize($document1);
        $adapter->serialize($document2);

        // Verify cache was accessed twice (once per document)
        $this->assertSame(2, $cacheCallCount);
    }

    public function testCustomCacheIsUsed(): void
    {
        $customCache = $this->createMock(CacheInterface::class);
        $adapter = new JsonStreamerAdapter(
            $this->decoratedSerializer,
            $this->streamWriter,
            $customCache
        );

        $document = new class {
            public string $name = 'test';
        };

        $this->decoratedSerializer
            ->method('serialize')
            ->willReturn('{"name":"test"}')
        ;

        $customCache
            ->expects($this->once())
            ->method('get')
            ->willReturnCallback(fn ($key, $callback) => $callback())
        ;

        $adapter->serialize($document);
    }

    public function testDefaultCacheIsArrayAdapter(): void
    {
        $adapter = new JsonStreamerAdapter(
            $this->decoratedSerializer,
            $this->streamWriter
        );

        $document = new class {
            public string $name = 'test';
        };

        $this->decoratedSerializer
            ->method('serialize')
            ->willReturn('{"name":"test"}')
        ;

        // This should work without errors using the default ArrayAdapter
        $adapter->serialize($document);
        $this->assertTrue(true);
    }

    public function testSerializeDifferentDocumentsWithDifferentSupport(): void
    {
        $supportedDocument = new JsonStreamableTestDocument();
        $unsupportedDocument = new class {
            public string $name = 'test';
        };

        $this->streamWriter
            ->expects($this->once())
            ->method('write')
            ->with($supportedDocument)
            ->willReturn(new StringableTraversable('{"items":[]}'))
        ;

        $expectedUnsupported = '{"name":"test"}';
        $this->decoratedSerializer
            ->expects($this->once())
            ->method('serialize')
            ->with($unsupportedDocument)
            ->willReturn($expectedUnsupported)
        ;

        // Serialize supported document using streamWriter
        $result1 = $this->adapter->serialize($supportedDocument);
        $this->assertSame('{"items":[]}', $result1);

        // Serialize unsupported document using decorated serializer
        $result2 = $this->adapter->serialize($unsupportedDocument);
        $this->assertSame($expectedUnsupported, $result2);
    }

    public function testMultipleUnsupportedDocuments(): void
    {
        $unsupported1 = new class {
            public string $name = 'test1';
        };
        $unsupported2 = new class {
            public string $name = 'test2';
        };

        $this->decoratedSerializer
            ->expects($this->exactly(2))
            ->method('serialize')
            ->willReturnOnConsecutiveCalls(
                '{"name":"test1"}',
                '{"name":"test2"}'
            )
        ;

        $this->streamWriter->expects($this->never())->method('write');

        $result1 = $this->adapter->serialize($unsupported1);
        $result2 = $this->adapter->serialize($unsupported2);

        $this->assertSame('{"name":"test1"}', $result1);
        $this->assertSame('{"name":"test2"}', $result2);
    }

    public function testStreamWriterResultConvertedToStringOnce(): void
    {
        $document = new JsonStreamableTestDocument();
        $result = new StringableTraversable('{"test":"data"}');

        $this->streamWriter
            ->expects($this->once())
            ->method('write')
            ->willReturn($result)
        ;

        $serialized = $this->adapter->serialize($document);

        // Verify the result is converted to string exactly once
        $this->assertIsString($serialized);
        $this->assertSame('{"test":"data"}', $serialized);
    }
}

#[JsonStreamable]
final class JsonStreamableTestDocument
{
    public array $items = [1, 2, 3];
}

/**
 * Helper class that implements both Stringable and Iterator for mocking StreamWriter results.
 */
final class StringableTraversable implements \Stringable, \Iterator
{
    private int $position = 0;
    private array $data = [];

    public function __construct(private string $content)
    {
        $this->data = [$content];
    }

    public function __toString(): string
    {
        return $this->content;
    }

    public function current(): mixed
    {
        return $this->data[$this->position] ?? null;
    }

    public function key(): mixed
    {
        return $this->position;
    }

    public function next(): void
    {
        ++$this->position;
    }

    public function rewind(): void
    {
        $this->position = 0;
    }

    public function valid(): bool
    {
        return isset($this->data[$this->position]);
    }
}
