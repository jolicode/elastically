<?php

/*
 * This file is part of the jolicode/elastically library.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JoliCode\Elastically\Bridge\Symfony\DataCollector;

use Elastica\Exception\ExceptionInterface;
use JoliCode\Elastically\IndexNameMapper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\HttpKernel\DataCollector\LateDataCollectorInterface;

final class ElasticallyDataCollector extends DataCollector implements LateDataCollectorInterface
{
    /**
     * Above this number, the hydrated models of a request are counted but not dumped.
     */
    private const MAX_DUMPED_MODELS = 50;

    /** @var array<string, array{client: TraceableClient, result_set_builder: TraceableResultSetBuilder, index_name_mapper: IndexNameMapper}> */
    private array $connections = [];

    public function addClient(string $connection, TraceableClient $client, TraceableResultSetBuilder $resultSetBuilder, IndexNameMapper $indexNameMapper): void
    {
        $this->connections[$connection] = [
            'client' => $client,
            'result_set_builder' => $resultSetBuilder,
            'index_name_mapper' => $indexNameMapper,
        ];
    }

    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        // Everything is collected in lateCollect(), to catch the requests sent after the response
    }

    public function lateCollect(): void
    {
        $this->data = [
            'connections' => [],
            'request_count' => 0,
            'error_count' => 0,
            'duplicate_count' => 0,
            'model_count' => 0,
            'duration' => 0.0,
        ];

        foreach ($this->connections as $connection => ['client' => $client, 'result_set_builder' => $resultSetBuilder, 'index_name_mapper' => $indexNameMapper]) {
            $traces = $client->getTraces();

            $hydrations = [];
            foreach ($resultSetBuilder->getHydrations() as $hydration) {
                if (null !== $hydration['request']) {
                    $hydrations[$hydration['request']][] = $hydration;
                }
            }

            $occurrences = [];
            foreach ($traces as $trace) {
                $key = $this->getDuplicateKey($trace);
                $occurrences[$key] = ($occurrences[$key] ?? 0) + 1;
            }

            $requests = [];
            foreach ($traces as $i => $trace) {
                $requests[] = $this->buildRequest($trace, $occurrences[$this->getDuplicateKey($trace)], $hydrations[$i] ?? [], $indexNameMapper);
            }

            $hosts = $client->getConfig('hosts');

            $this->data['connections'][$connection] = [
                'hosts' => array_map($this->removeCredentials(...), \is_array($hosts) ? $hosts : [$hosts]),
                'indices' => $this->buildMappedIndices($indexNameMapper),
                'requests' => $requests,
            ];

            $this->data['request_count'] += \count($requests);
            foreach ($requests as $request) {
                $this->data['duration'] += $request['duration'];
                $this->data['model_count'] += $request['model_count'];
                if ($request['is_error']) {
                    ++$this->data['error_count'];
                }
            }
            foreach ($occurrences as $count) {
                $this->data['duplicate_count'] += $count - 1;
            }
        }
    }

    /**
     * @return array<string, array{hosts: list<string>, indices: list<array{name: string, prefixed_name: string, class: string|null}>, requests: list<array<string, mixed>>}>
     */
    public function getConnections(): array
    {
        return $this->data['connections'] ?? [];
    }

    public function getRequestCount(): int
    {
        return $this->data['request_count'] ?? 0;
    }

    public function getErrorCount(): int
    {
        return $this->data['error_count'] ?? 0;
    }

    public function getDuplicateCount(): int
    {
        return $this->data['duplicate_count'] ?? 0;
    }

    public function getModelCount(): int
    {
        return $this->data['model_count'] ?? 0;
    }

    /**
     * In milliseconds.
     */
    public function getDuration(): float
    {
        return $this->data['duration'] ?? 0.0;
    }

    public function getName(): string
    {
        return 'elastically';
    }

    public function reset(): void
    {
        $this->data = [];

        foreach ($this->connections as ['client' => $client, 'result_set_builder' => $resultSetBuilder]) {
            $client->reset();
            $resultSetBuilder->reset();
        }
    }

    /**
     * @param array{method: string, url: string, request_body: string, status_code: int|null, response_body: string|null, duration: float, error: string|null} $trace
     * @param list<array{request: int|null, index: string|null, id: string|null, model: mixed}>                                                                $hydrations
     *
     * @return array<string, mixed>
     */
    private function buildRequest(array $trace, int $occurrences, array $hydrations, IndexNameMapper $indexNameMapper): array
    {
        $path = parse_url($trace['url'], \PHP_URL_PATH) ?: '/';
        if ($query = parse_url($trace['url'], \PHP_URL_QUERY)) {
            $path .= '?' . $query;
        }

        $isNdjson = false;
        $requestBody = $this->decodeBody($trace['request_body'], $isNdjson);

        return [
            'method' => $trace['method'],
            'url' => $trace['url'],
            'path' => $path,
            'status_code' => $trace['status_code'],
            'duration' => $trace['duration'],
            'error' => $trace['error'],
            'is_error' => $this->isError($trace),
            'occurrences' => $occurrences,
            'indices' => $this->buildTargetedIndices($path, $isNdjson ? $requestBody : null, $indexNameMapper),
            'model_count' => \count($hydrations),
            'models' => $this->buildModels(\array_slice($hydrations, 0, self::MAX_DUMPED_MODELS), $indexNameMapper),
            'request_body' => null === $requestBody ? null : $this->cloneVar($requestBody),
            'response_body' => '' === trim($trace['response_body'] ?? '') ? null : $this->cloneVar($this->decodeBody($trace['response_body'])),
            'curl_command' => $this->buildCurlCommand($trace, $isNdjson),
        ];
    }

    /**
     * @return list<array{name: string, prefixed_name: string, class: string|null}>
     */
    private function buildMappedIndices(IndexNameMapper $indexNameMapper): array
    {
        $indices = [];
        foreach ($indexNameMapper->getMappedIndices() as $name) {
            $indices[] = [
                'name' => $name,
                'prefixed_name' => $indexNameMapper->getPrefixedIndex($name),
                'class' => $this->findClass($indexNameMapper, $name),
            ];
        }

        return $indices;
    }

    /**
     * Lists the indices targeted by the request: the ones in the path, and the ones of each operation of a bulk request.
     *
     * @return list<array{name: string, pure_name: string, class: string|null}>
     */
    private function buildTargetedIndices(string $path, mixed $ndjsonLines, IndexNameMapper $indexNameMapper): array
    {
        $names = [];

        $segment = urldecode(explode('/', ltrim(parse_url($path, \PHP_URL_PATH) ?: '', '/'))[0]);
        if ('' !== $segment && !str_starts_with($segment, '_')) {
            $names = explode(',', $segment);
        }

        foreach (\is_array($ndjsonLines) ? $ndjsonLines : [] as $line) {
            // The action line of a bulk operation, like {"index": {"_index": "beers", "_id": "1"}}
            if (\is_array($line) && 1 === \count($line) && \is_array($action = reset($line)) && \is_string($action['_index'] ?? null)) {
                $names[] = $action['_index'];
            }
        }

        $indices = [];
        foreach (array_unique($names) as $name) {
            $pureName = $indexNameMapper->getPureIndexName($name);

            $indices[] = [
                'name' => $name,
                'pure_name' => $pureName,
                'class' => $this->findClass($indexNameMapper, $pureName),
            ];
        }

        return $indices;
    }

    private function findClass(IndexNameMapper $indexNameMapper, string $pureIndexName): ?string
    {
        try {
            return $indexNameMapper->getClassFromIndexName($pureIndexName);
        } catch (ExceptionInterface) {
            return null;
        }
    }

    /**
     * @param list<array{request: int|null, index: string|null, id: string|null, model: mixed}> $hydrations
     *
     * @return list<array<string, mixed>>
     */
    private function buildModels(array $hydrations, IndexNameMapper $indexNameMapper): array
    {
        $models = [];
        foreach ($hydrations as $hydration) {
            $models[] = [
                'index' => $hydration['index'],
                'pure_index' => null !== $hydration['index'] ? $indexNameMapper->getPureIndexName($hydration['index']) : null,
                'id' => $hydration['id'],
                'class' => get_debug_type($hydration['model']),
                'model' => $this->cloneVar($hydration['model']),
            ];
        }

        return $models;
    }

    /**
     * @param array{method: string, status_code: int|null, error: string|null} $trace
     */
    private function isError(array $trace): bool
    {
        if (null !== $trace['error']) {
            return true;
        }

        // A 404 is the expected answer of HEAD requests on a missing resource, like Index::exists()
        if ('HEAD' === $trace['method'] && 404 === $trace['status_code']) {
            return false;
        }

        return ($trace['status_code'] ?? 0) >= 400;
    }

    private function decodeBody(string $body, bool &$isNdjson = false): mixed
    {
        $isNdjson = false;

        if ('' === trim($body)) {
            return null;
        }

        try {
            return json_decode($body, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
        }

        // Bulk requests use NDJSON
        $lines = [];
        foreach (explode("\n", trim($body)) as $line) {
            try {
                $lines[] = json_decode($line, true, 512, \JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                return $body;
            }
        }

        $isNdjson = true;

        return $lines;
    }

    /**
     * @param array{method: string, url: string, request_body: string} $trace
     */
    private function buildCurlCommand(array $trace, bool $isNdjson): string
    {
        $command = \sprintf('curl -X %s %s', $trace['method'], escapeshellarg($trace['url']));

        if ('' !== trim($trace['request_body'])) {
            $command .= \sprintf(
                ' -H %s --data-binary %s',
                escapeshellarg('Content-Type: ' . ($isNdjson ? 'application/x-ndjson' : 'application/json')),
                escapeshellarg($isNdjson ? rtrim($trace['request_body'], "\n") . "\n" : $trace['request_body']),
            );
        }

        return $command;
    }

    /**
     * @param array{method: string, url: string, request_body: string} $trace
     */
    private function getDuplicateKey(array $trace): string
    {
        return $trace['method'] . ' ' . $trace['url'] . "\n" . $trace['request_body'];
    }

    private function removeCredentials(mixed $host): string
    {
        return preg_replace('{//[^/@]*@}', '//', (string) $host) ?? '';
    }
}
