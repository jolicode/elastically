<?php
include "vendor/autoload.php";

use Elastica\Client;
use Elastica\Document;
use Elastica\Query\BoolQuery;
use Elastica\Query\Match;

class Product
{
    protected $name;
    protected $category;

    public function getName()
    {
        return $this->name;
    }
    public function setName($name): void
    {
        $this->name = $name;
    }
    public function getCategory()
    {
        return $this->category;
    }
    public function setCategory($category): void
    {
        $this->category = $category;
    }
}

$serializer = new Symfony\Component\Serializer\Serializer([
    new \Symfony\Component\Serializer\Normalizer\ArrayDenormalizer(),
    new \Symfony\Component\Serializer\Normalizer\ObjectNormalizer(),
], [
    new \Symfony\Component\Serializer\Encoder\JsonEncoder()
]);

$product = new Product();
$product->setName('WashWash 3000');
$product->setCategory('Dentifrice');

$doc = new Document(42, $serializer->serialize($product, 'json'));

$client = new Client();
$indexer = new \JoliCode\Elastically\Indexer($client);
$index = $client->getIndex('app');

$indexer->scheduleInsert($index, $doc);
$indexer->scheduleInsert($index, $doc);
$indexer->scheduleInsert($index, $doc);
$indexer->scheduleInsert($index, $doc);
$indexer->scheduleInsert($index, $doc);
$indexer->flush();


$query = new \Elastica\Query\Match('name', 'washwash');
$results = $index->search($query);
var_dump($results->getResults()[0]->getDocument());

$search = $index->createSearch($query, null, new \JoliCode\Elastically\ResultSetBuilder($serializer));

var_dump($search->search());

// Et pour get 1 seul doc ?

$oneDoc = $index->getType('_doc')->getDocument(42);
$oneDoc->setData($serializer->denormalize($oneDoc->getData(), \Product::class));
var_dump($oneDoc);
