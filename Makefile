test: ## Run test suite
	./vendor/bin/simple-phpunit

start_7: ## Start testing tools (Elasticsearch 7)
	docker run --rm -d --name "elastically_es" -p 9999:9200 -e "discovery.type=single-node" docker.elastic.co/elasticsearch/elasticsearch:7.17.25

start: ## Start testing tools (Elasticsearch 8)
	docker run --rm -d --name "elastically_es" -p 9999:9200 -e "discovery.type=single-node" -e "xpack.security.enabled=false" -e "action.destructive_requires_name=false" -it -m 1GB docker.elastic.co/elasticsearch/elasticsearch:8.16.0

#start_opensearch: ## Start testing tools (OpenSearch)
#	docker run --rm -d --name "elastically_es" -p 9999:9200 -e "discovery.type=single-node" -e "DISABLE_SECURITY_PLUGIN=true" opensearchproject/opensearch:2

stop: ## Stop testing tools
	docker stop "elastically_es"

kibana: ## Start debug tools (Kibana)
	docker run -e "ELASTICSEARCH_HOSTS=http://127.0.0.1:9999/" --network host docker.elastic.co/kibana/kibana:7.17.25

cs: ## Fix PHP CS
	./vendor/bin/php-cs-fixer fix --verbose

phpstan: # Run phpstan
	./vendor/bin/phpstan analyse

.PHONY: help

help: ## Display this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-8s\033[0m %s\n", $$1, $$2}'

.DEFAULT_GOAL := help
