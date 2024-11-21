# Upgrade guide

## From v1.9.0 to v2.0.0

> [!WARNING]  
> All the deprecation from Elastica 7 to Elastica 8 apply to this major version,
> [see their UPGRADE first](https://github.com/ruflin/Elastica/blob/58042887616eeb63621412c03bc903056bbcee7e/UPGRADE-8.0.md#upgrade-from-73-to-80).

HttpClientTransport has been removed, you must replace it with [a PSR-18 client](https://symfony.com/doc/current/http_client.html#psr-18-and-psr-17):

```diff
-    transport: '@JoliCode\Elastically\Transport\HttpClientTransport'
+    transport_config:
+        http_client: 'Psr\Http\Client\ClientInterface'
```

Code has been cleaned of deprecations:

- Removed `\JoliCode\Elastically\Index::getBuilder` method, use `\JoliCode\Elastically\Factory::buildIndexBuilder` if needed
- Removed all const in `\JoliCode\Elastically\Client`, use the one in `\JoliCode\Elastically\Factory` instead:
    - `\JoliCode\Elastically\Client::CONFIG_MAPPINGS_DIRECTORY` => `\JoliCode\Elastically\Factory::CONFIG_MAPPINGS_DIRECTORY`
    - `\JoliCode\Elastically\Client::CONFIG_SERIALIZER_CONTEXT_PER_CLASS` => `\JoliCode\Elastically\Factory::CONFIG_SERIALIZER_CONTEXT_PER_CLASS`
    - `\JoliCode\Elastically\Client::CONFIG_SERIALIZER` => `\JoliCode\Elastically\Factory::CONFIG_SERIALIZER`
    - `\JoliCode\Elastically\Client::CONFIG_BULK_SIZE` => `\JoliCode\Elastically\Factory::CONFIG_BULK_SIZE`
    - `\JoliCode\Elastically\Client::CONFIG_INDEX_PREFIX` => `\JoliCode\Elastically\Factory::CONFIG_INDEX_PREFIX`
    - `\JoliCode\Elastically\Client::CONFIG_INDEX_CLASS_MAPPING` => `\JoliCode\Elastically\Factory::CONFIG_INDEX_CLASS_MAPPING`
- `\JoliCode\Elastically\Client::__construct` now requires ResultSetBuilder and IndexNameMapper, use dependency injection
- Removed `\JoliCode\Elastically\Client::getPrefixedIndex`, use `\JoliCode\Elastically\IndexNameMapper::getPrefixedIndex`
- Removed `\JoliCode\Elastically\Client::getIndexNameFromClass`, use `\JoliCode\Elastically\IndexNameMapper::getIndexNameFromClass`
- Removed `\JoliCode\Elastically\Client::getClassFromIndexName`, use `\JoliCode\Elastically\IndexNameMapper::getClassFromIndexName`
- Removed `\JoliCode\Elastically\Client::getPureIndexName`, use `\JoliCode\Elastically\IndexNameMapper::getPureIndexName`
- Removed `\JoliCode\Elastically\Client::getIndexBuilder`, use the Factory (`\JoliCode\Elastically\Factory`) or DIC
- Removed `\JoliCode\Elastically\Client::getIndexer`, use the Factory (`\JoliCode\Elastically\Factory`) or DIC
- Removed `\JoliCode\Elastically\Client::getBuilder`, use the Factory (`\JoliCode\Elastically\Factory`) or DIC
- Removed `\JoliCode\Elastically\Client::getSerializer`, use the Factory (`\JoliCode\Elastically\Factory`) or DIC
- Removed `\JoliCode\Elastically\Client::getDenormalizer`, use the Factory (`\JoliCode\Elastically\Factory`) or DIC
- Removed `\JoliCode\Elastically\Client::getSerializerContext`, use the Factory (`\JoliCode\Elastically\Factory`) or DIC
- Using `\Elastica\Document::setData` to store your DTO will not work anymore, you must use `\JoliCode\Elastically\Model\Document` instead

## From v1.3.0 to v1.4.0

If you're using Symfony, here are the changes to apply:

```diff
services:
    _defaults:
        autowire: true
        autoconfigure: true

+    JoliCode\Elastically\IndexNameMapper:
+        arguments:
+            $prefix: null # or a string to prefix index name
+            $indexClassMapping:
+                indexName: My\AwesomeDTO
+
+    JoliCode\Elastically\Serializer\StaticContextBuilder:
+        arguments:
+            $mapping: []
+
+    JoliCode\Elastically\ResultSetBuilder:
+        arguments:
+            $indexNameMapper: '@JoliCode\Elastically\IndexNameMapper'
+            $contextBuilder: '@JoliCode\Elastically\Serializer\StaticContextBuilder'
+            $denormalizer: '@serializer'

    JoliCode\Elastically\Client:
        arguments:
            $config:
                host: '%env(ELASTICSEARCH_HOST)%'
                port: '%env(ELASTICSEARCH_PORT)%'
                transport: '@JoliCode\Elastically\Transport\HttpClientTransport'
                elastically_mappings_directory: '%kernel.project_dir%/config/elasticsearch'
-                elastically_index_class_mapping:
-                    news: indexName: My\AwesomeDTO
-                elastically_serializer: '@serializer'
-                elastically_bulk_size: 100
                elastically_index_prefix: $prefix: null # or a string to prefix index name
            $logger: '@logger'
+            $resultSetBuilder: '@JoliCode\Elastically\ResultSetBuilder'
+            $indexNameMapper: '@JoliCode\Elastically\IndexNameMapper'
+
+    JoliCode\Elastically\Indexer:
+        arguments:
+            $client: '@JoliCode\Elastically\Client'
+            $serializer: '@serializer'
+            $bulkMaxSize: 100
+            $bulkRequestParams: []
+            $contextBuilder: '@JoliCode\Elastically\Serializer\StaticContextBuilder'
+
+    JoliCode\Elastically\Mapping\YamlProvider:
+        arguments:
+            $configurationDirectory: '%kernel.project_dir%/config/elasticsearch'
+
+    JoliCode\Elastically\IndexBuilder:
+        arguments:
+            $mappingProvider: '@JoliCode\Elastically\Mapping\YamlProvider'
+            $client: '@JoliCode\Elastically\Client'
+            $indexNameMapper: '@JoliCode\Elastically\IndexNameMapper'

    JoliCode\Elastically\Transport\HttpClientTransport: ~
    JoliCode\Elastically\Messenger\IndexationRequestHandler: ~

    JoliCode\Elastically\Messenger\DocumentExchangerInterface:
        alias: App\Elasticsearch\DocumentExchanger
```
