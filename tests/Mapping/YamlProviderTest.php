<?php

/*
 * This file is part of the jolicode/elastically library.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JoliCode\Elastically\Tests\Mapping;

use Elastica\Exception\InvalidException;
use JoliCode\Elastically\Mapping\YamlProvider;
use PHPUnit\Framework\TestCase;

final class YamlProviderTest extends TestCase
{
    public function testNonExistentFileThrowsException(): void
    {
        $this->expectException(InvalidException::class);
        $provider = new YamlProvider(__DIR__ . '/../configs');
        $provider->provideMapping('unknown');
    }

    public function testMappingIsNullWhenConfigIsEmpty(): void
    {
        $provider = new YamlProvider(__DIR__ . '/../configs');
        self::assertNull($provider->provideMapping('empty'));
    }

    public function testMappingContainsConfiguredAnalyzers(): void
    {
        $provider = new YamlProvider(__DIR__ . '/../configs_analysis');
        $beerMapping = $provider->provideMapping('foo');
        // Make sure the structure has both mapping & configured analyzers.
        self::assertSame([
            'mappings' => [
                'properties' => [
                    'name' => ['type' => 'text'],
                ],
            ],
            'settings' => [
                'analysis' => [
                    'analyzer' => [
                        'beer_name' => [
                            'tokenizer' => 'standard',
                            'filter' => ['asciifolding'],
                        ],
                    ],
                ],
            ],
        ], $beerMapping);
    }

    public function testMappingDirectoryCanBeAGlobPattern(): void
    {
        $provider = new YamlProvider(__DIR__ . '/../configs_glob/*/mapping');

        self::assertSame([
            'mappings' => [
                'properties' => [
                    'name' => ['type' => 'text'],
                ],
            ],
        ], $provider->provideMapping('foo'));

        // Analyzers are loaded from the directory where the mapping has been found
        self::assertSame([
            'mappings' => [
                'properties' => [
                    'name' => ['type' => 'keyword'],
                ],
            ],
            'settings' => [
                'analysis' => [
                    'analyzer' => [
                        'bar_name' => [
                            'tokenizer' => 'standard',
                        ],
                    ],
                ],
            ],
        ], $provider->provideMapping('bar'));
    }

    public function testGlobPatternWithoutMatchingFileThrowsException(): void
    {
        $this->expectException(InvalidException::class);
        $this->expectExceptionMessage('not found');
        $provider = new YamlProvider(__DIR__ . '/../configs_glob/*/mapping');
        $provider->provideMapping('unknown');
    }

    public function testGlobPatternWithSeveralMatchingFilesThrowsException(): void
    {
        $this->expectException(InvalidException::class);
        $this->expectExceptionMessage('found in several directories');
        $provider = new YamlProvider(__DIR__ . '/../configs_glob/*/mapping');
        $provider->provideMapping('duplicated');
    }
}
