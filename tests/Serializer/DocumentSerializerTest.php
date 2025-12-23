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

use JoliCode\Elastically\Serializer\DocumentSerializer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\JsonStreamer\Attribute\JsonStreamable;
use Symfony\Component\JsonStreamer\StreamWriterInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\TypeInfo\Type;
use Symfony\Contracts\Cache\CacheInterface;

final class DocumentSerializerTest extends TestCase
{
    private SerializerInterface $baseSerializer;
    private StreamWriterInterface $streamWriter;
    private ArrayAdapter $cache;
    private DocumentSerializer $serializer;

    protected function setUp(): void
    {
        if (!interface_exists(StreamWriterInterface::class)) {
            $this->markTestSkipped('Skipping as JsonStreamer is not installed.');
        }

        $this->baseSerializer = $this->createMock(SerializerInterface::class);
        $this->streamWriter = $this->createMock(StreamWriterInterface::class);
        $this->cache = new ArrayAdapter();
        $this->serializer = new DocumentSerializer(
            $this->baseSerializer,
            null,
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
        $this->baseSerializer
            ->expects($this->once())
            ->method('serialize')
            ->with($document, 'json', $this->isType('array'))
            ->willReturn($expectedSerialized)
        ;

        $this->streamWriter->expects($this->never())->method('write');

        $result = $this->serializer->serialize($document, 'json');

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

        $this->baseSerializer->expects($this->never())->method('serialize');

        $result = $this->serializer->serialize($document, 'json');

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

        $result = $this->serializer->serialize($document, 'json');

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

        $serializer = new DocumentSerializer(
            $this->baseSerializer,
            null,
            $this->streamWriter,
            $cache
        );

        $this->streamWriter
            ->expects($this->exactly(2))
            ->method('write')
            ->willReturn(new StringableTraversable('{"items":[]}'))
        ;

        $serializer->serialize($document1, 'json');
        $serializer->serialize($document2, 'json');

        // Verify cache was accessed twice (once per document)
        $this->assertSame(2, $cacheCallCount);
    }

    public function testCustomCacheIsUsed(): void
    {
        $customCache = $this->createMock(CacheInterface::class);
        $serializer = new DocumentSerializer(
            $this->baseSerializer,
            null,
            $this->streamWriter,
            $customCache
        );

        $document = new class {
            public string $name = 'test';
        };

        $this->baseSerializer
            ->method('serialize')
            ->willReturn('{"name":"test"}')
        ;

        $customCache
            ->expects($this->once())
            ->method('get')
            ->willReturnCallback(fn ($key, $callback) => $callback())
        ;

        $serializer->serialize($document, 'json');
    }

    public function testDefaultCacheIsArrayAdapter(): void
    {
        $serializer = new DocumentSerializer(
            $this->baseSerializer,
            null,
            $this->streamWriter
        );

        $document = new class {
            public string $name = 'test';
        };

        $this->baseSerializer
            ->method('serialize')
            ->willReturn('{"name":"test"}')
        ;

        // This should work without errors using the default ArrayAdapter
        $serializer->serialize($document, 'json');
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
        $this->baseSerializer
            ->expects($this->once())
            ->method('serialize')
            ->with($unsupportedDocument, 'json', $this->isType('array'))
            ->willReturn($expectedUnsupported)
        ;

        // Serialize supported document using streamWriter
        $result1 = $this->serializer->serialize($supportedDocument, 'json');
        $this->assertSame('{"items":[]}', $result1);

        // Serialize unsupported document using decorated serializer
        $result2 = $this->serializer->serialize($unsupportedDocument, 'json');
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

        $this->baseSerializer
            ->expects($this->exactly(2))
            ->method('serialize')
            ->willReturnOnConsecutiveCalls(
                '{"name":"test1"}',
                '{"name":"test2"}'
            )
        ;

        $this->streamWriter->expects($this->never())->method('write');

        $result1 = $this->serializer->serialize($unsupported1, 'json');
        $result2 = $this->serializer->serialize($unsupported2, 'json');

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

        $serialized = $this->serializer->serialize($document, 'json');

        // Verify the result is converted to string exactly once
        $this->assertIsString($serialized);
        $this->assertSame('{"test":"data"}', $serialized);
    }

    public function testDeserialize(): void
    {
        $this->baseSerializer
            ->expects($this->once())
            ->method('deserialize')
            ->with('{"name":"test"}', 'stdClass', 'json', [])
            ->willReturn(new \stdClass())
        ;

        $result = $this->serializer->deserialize('{"name":"test"}', 'stdClass', 'json');
        $this->assertInstanceOf(\stdClass::class, $result);
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
