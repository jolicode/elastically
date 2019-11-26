test: ## Run test suite
	./vendor/bin/phpunit --bootstrap vendor/autoload.php --testdox --colors=always tests

start: ## Start testing tools (Elasticsearch)
	docker run -d --name "elastically_es" -p 9200:9200 -e "discovery.type=single-node" docker.elastic.co/elasticsearch/elasticsearch-oss:7.4.0

stop: ## Stop testing tools (Elasticsearch)
	docker stop "elastically_es"

kibana: ## Start debug tools (Kibana)
	docker run -e "ELASTICSEARCH_HOSTS=http://127.0.0.1:9200/" --network host docker.elastic.co/kibana/kibana-oss:7.4.0

cs: ## Fix PHP CS
	./vendor/bin/php-cs-fixer fix --verbose --rules=@Symfony,ordered_imports src/
	./vendor/bin/php-cs-fixer fix --verbose --rules=@Symfony,ordered_imports tests/

.PHONY: help

help: ## Display this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-8s\033[0m %s\n", $$1, $$2}'

.DEFAULT_GOAL := help
