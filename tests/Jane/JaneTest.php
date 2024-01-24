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

namespace JoliCode\Elastically\Tests\Jane;

use Jane\Component\JsonSchemaGenerator\Configuration;
use Jane\Component\JsonSchemaGenerator\Generator;
use JoliCode\Elastically\Client;
use JoliCode\Elastically\IndexBuilder;
use JoliCode\Elastically\Indexer;
use JoliCode\Elastically\IndexNameMapper;
use JoliCode\Elastically\Mapping\YamlProvider;
use JoliCode\Elastically\Model\Document;
use JoliCode\Elastically\ResultSetBuilder;
use JoliCode\Elastically\Serializer\StaticContextBuilder;
use JoliCode\Elastically\Tests\Jane\generated\Model\MyModel;
use JoliCode\Elastically\Tests\Jane\generated\Model\MyModelIngredients;
use JoliCode\Elastically\Tests\Jane\generated\Normalizer\JaneNormalizer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Encoder\JsonDecode;
use Symfony\Component\Serializer\Encoder\JsonEncode;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Serializer;

class JaneTest extends TestCase
{
    public function testCreateIndexAndSearchWithJaneObject()
    {
        // Build the models
        $generator = new Generator(new Configuration(
            outputDirectory: __DIR__ . '/generated/',
            baseNamespace: 'JoliCode\\Elastically\\Tests\\Jane\\generated',
        ));
        $generator->fromPath(__DIR__ . '/schema.json', 'MyModel');

        // Build the Serializer
        $normalizers = [
            new ArrayDenormalizer(),
            new JaneNormalizer(),
        ];
        $encoders = [
            new JsonEncoder(
                new JsonEncode(),
                new JsonDecode([JsonDecode::ASSOCIATIVE => true])
            ),
        ];

        $serializer = new Serializer($normalizers, $encoders);

        $resultSetBuilder = new ResultSetBuilder($indexNameMapper = new IndexNameMapper(null, ['beers' => MyModel::class]), new StaticContextBuilder(), $serializer);

        // Build Elastically Client
        $elastically = new Client(
            ['port' => '9999'],
            null,
            null,
            $resultSetBuilder,
            $indexNameMapper
        );

        $indexBuilder = new IndexBuilder(new YamlProvider(__DIR__ . '/../configs'), $elastically, $indexNameMapper);
        $indexer = new Indexer($elastically, $serializer);

        // Build Index
        $index = $indexBuilder->createIndex('beers');
        $indexBuilder->markAsLive($index, 'beers');

        // Create a DTO
        $ingredient1 = new MyModelIngredients('Water');
        $ingredient2 = new MyModelIngredients('Malt');
        $ingredient3 = new MyModelIngredients('Hops');
        $dto = new MyModel('La Montreuilloise Smoked Porter', 3.20, [$ingredient1, $ingredient2, $ingredient3]);

        // Index the DTO
        $indexer->scheduleIndex('beers', new Document('123', $dto));
        $indexer->flush();
        $indexer->refresh('beers');

        // Search
        $results = $elastically->getIndex('beers')->search();

        $this->assertInstanceOf(MyModel::class, $results->getResults()[0]->getModel());

        $elasticallyDto = $results->getResults()[0]->getModel();

        // DTO are not the same object but are identical
        $this->assertNotSame($dto, $elasticallyDto);
        $this->assertSame(serialize($dto), serialize($elasticallyDto));
    }
}
