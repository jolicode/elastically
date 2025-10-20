<?php

/*
 * This file is part of the jolicode/elastically library.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Castor\Attribute\AsRawTokens;
use Castor\Attribute\AsTask;

use function Castor\run;

#[AsTask(description: 'Run test suite')]
function test(#[AsRawTokens] array $rawTokens = []): void
{
    run('vendor/bin/simple-phpunit ' . implode(' ', $rawTokens));
}

#[AsTask(description: 'Start testing tools (Elasticsearch 7)')]
function start_7(): void
{
    run('docker run --rm -d --name "elastically_es" -p 9999:9200 -e "discovery.type=single-node" docker.elastic.co/elasticsearch/elasticsearch:7.17.25');
}

#[AsTask(description: 'Start testing tools (Elasticsearch 8)')]
function start(): void
{
    run('docker run --rm -d --name "elastically_es" -p 9999:9200 -e "discovery.type=single-node" -e "xpack.security.enabled=false" -e "action.destructive_requires_name=false" -it -m 1GB docker.elastic.co/elasticsearch/elasticsearch:8.16.0');
}

#[AsTask(description: 'Stop testing tools')]
function stop(): void
{
    run('docker stop "elastically_es"');
}

#[AsTask(description: 'Start debug tools (Kibana)')]
function kibana(): void
{
    run('docker run -e "ELASTICSEARCH_HOSTS=http://127.0.0.1:9999/" --network host docker.elastic.co/kibana/kibana:7.17.25');
}

#[AsTask(description: 'Fix PHP CS')]
function cs(): void
{
    run('vendor/bin/php-cs-fixer fix --verbose');
}

#[AsTask(description: 'Run phpstan')]
function phpstan(): void
{
    run('vendor/bin/phpstan analyse');
}
