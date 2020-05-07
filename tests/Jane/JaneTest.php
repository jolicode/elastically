<?php

declare(strict_types=1);

namespace JoliCode\Elastically\Tests\Jane;

use Elastica\Document;
use Jane\JsonSchema\Console\Command\GenerateCommand;
use Jane\JsonSchema\Console\Loader\ConfigLoader;
use Jane\JsonSchema\Console\Loader\SchemaLoader;
use JoliCode\Elastically\Client;
use JoliCode\Elastically\Tests\Jane\generated\Model\MyModel;
use JoliCode\Elastically\Tests\Jane\generated\Model\MyModelIngredientsItemAnyOf;
use JoliCode\Elastically\Tests\Jane\generated\Normalizer\JaneObjectNormalizer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
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
        $command = new GenerateCommand(new ConfigLoader(), new SchemaLoader());
        $inputArray = new ArrayInput([
            '--config-file' => __DIR__.'/jane-config.php',
        ], $command->getDefinition());

        $command->execute($inputArray, new NullOutput());

        // Build the Serializer
        $normalizers = [
            new ArrayDenormalizer(),
            new JaneObjectNormalizer(),
        ];
        $encoders = [new JsonEncoder(
            new JsonEncode(),
            new JsonDecode([JsonDecode::ASSOCIATIVE => true])),
        ];

        $serializer = new Serializer($normalizers, $encoders);

        // Build Elastically Client
        $elastically = new Client([
            Client::CONFIG_MAPPINGS_DIRECTORY => __DIR__.'/../configs',
            Client::CONFIG_INDEX_CLASS_MAPPING => [
                'beers' => MyModel::class,
            ],
            Client::CONFIG_SERIALIZER => $serializer,
        ]);

        // Build Index
        $indexBuilder = $elastically->getIndexBuilder();
        $index = $indexBuilder->createIndex('beers');
        $indexBuilder->markAsLive($index, 'beers');

        // Create a DTO
        $dto = new MyModel();
        $dto->setName('La Montreuilloise Smoked Porter');
        $dto->setPrice(3.20);
        $ingredient1 = new MyModelIngredientsItemAnyOf();
        $ingredient1->setName('Water');
        $ingredient2 = new MyModelIngredientsItemAnyOf();
        $ingredient2->setName('Malt');
        $ingredient3 = new MyModelIngredientsItemAnyOf();
        $ingredient3->setName('Hops');
        $dto->setIngredients([$ingredient1, $ingredient2, $ingredient3]);

        // Index the DTO
        $indexer = $elastically->getIndexer();
        $indexer->scheduleIndex('beers', new Document('123', $dto));
        $indexer->flush();
        $indexer->refresh('beers');

        // Search
        $results = $elastically->getIndex('beers')->search();

        $this->assertInstanceOf(MyModel::class, $results->getResults()[0]->getModel());

        $elasticallyDto = $results->getResults()[0]->getModel();

        // DTO are not the same object but are identical
        $this->assertNotSame($dto, $elasticallyDto);
        $this->assertEquals($dto, $elasticallyDto);
    }
}
