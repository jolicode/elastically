# Upgrade guide

## From v1.9.0 to v2.0.0

HttpClientTransport has been removed:
```diff
    JoliCode\Elastically\Client:
        arguments:
            $config:
                host: '%env(ELASTICSEARCH_HOST)%'
                port: '%env(ELASTICSEARCH_PORT)%'
-               transport: '@JoliCode\Elastically\Transport\HttpClientTransport'
+               transport_client:
+                   client: '@my_custom_psr18_client' # An instance of Symfony\Component\HttpClient\Psr18Client (Or any PSR 18 compliant one)
```

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
