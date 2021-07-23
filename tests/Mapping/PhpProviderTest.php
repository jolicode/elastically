<?php

declare(strict_types=1);

namespace JoliCode\Elastically\Tests\Mapping;

use Elastica\Exception\InvalidException;
use JoliCode\Elastically\Mapping\PhpProvider;
use PHPUnit\Framework\TestCase;

final class PhpProviderTest extends TestCase
{
    public function testNonExistentFileThrowsException(): void
    {
        $this->expectException(InvalidException::class);
        $provider = new PhpProvider(__DIR__.'/../configs');
        $provider->provideMapping('unknown');
    }

    public function testMappingIsNullWhenConfigIsEmpty(): void
    {
        $provider = new PhpProvider(__DIR__.'/../configs');
        self::assertNull($provider->provideMapping('empty'));
    }

    public function testMappingContainsConfiguredAnalyzers(): void
    {
        $provider = new PhpProvider(__DIR__.'/../configs_analysis');
        $beerMapping = $provider->provideMapping('foo');
        // Make sure the structure has both mapping & configured analyzers.
        self::assertSame([
            'mappings' => [
                'properties' => [
                    'name' => ['type' => 'keyword'],
                ],
            ],
            'settings' => [
                'analysis' => [
                    'analyzer' => [
                        'beer_name' => [
                            'tokenizer' => 'classic',
                            'filter' => ['elision'],
                        ],
                    ],
                ],
            ],
        ], $beerMapping);
    }
}
