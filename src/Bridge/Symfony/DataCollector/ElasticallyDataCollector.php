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

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\HttpKernel\DataCollector\LateDataCollectorInterface;

final class ElasticallyDataCollector extends DataCollector implements LateDataCollectorInterface
{
    /** @var array<string, TraceableClient> */
    private array $clients = [];

    public function addClient(string $connection, TraceableClient $client): void
    {
        $this->clients[$connection] = $client;
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
            'duration' => 0.0,
        ];

        foreach ($this->clients as $connection => $client) {
            $traces = $client->getTraces();

            $occurrences = [];
            foreach ($traces as $trace) {
                $key = $this->getDuplicateKey($trace);
                $occurrences[$key] = ($occurrences[$key] ?? 0) + 1;
            }

            $requests = [];
            foreach ($traces as $trace) {
                $requests[] = $this->buildRequest($trace, $occurrences[$this->getDuplicateKey($trace)]);
            }

            $hosts = $client->getConfig('hosts');

            $this->data['connections'][$connection] = [
                'hosts' => array_map($this->removeCredentials(...), \is_array($hosts) ? $hosts : [$hosts]),
                'requests' => $requests,
            ];

            $this->data['request_count'] += \count($requests);
            foreach ($requests as $request) {
                $this->data['duration'] += $request['duration'];
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
     * @return array<string, array{hosts: list<string>, requests: list<array<string, mixed>>}>
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

        foreach ($this->clients as $client) {
            $client->reset();
        }
    }

    /**
     * @param array{method: string, url: string, request_body: string, status_code: int|null, response_body: string|null, duration: float, error: string|null} $trace
     *
     * @return array<string, mixed>
     */
    private function buildRequest(array $trace, int $occurrences): array
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
            'request_body' => null === $requestBody ? null : $this->cloneVar($requestBody),
            'response_body' => '' === trim($trace['response_body'] ?? '') ? null : $this->cloneVar($this->decodeBody($trace['response_body'])),
            'curl_command' => $this->buildCurlCommand($trace, $isNdjson),
            'console_command' => $this->buildConsoleCommand($trace['method'], $path, $trace['request_body'], $isNdjson),
        ];
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
     * Builds a request that can be pasted in the Kibana Dev Tools console.
     */
    private function buildConsoleCommand(string $method, string $path, string $body, bool $isNdjson): string
    {
        $command = $method . ' ' . $path;

        if ('' === trim($body)) {
            return $command;
        }

        if ($isNdjson) {
            return $command . "\n" . trim($body);
        }

        try {
            // Decoded as objects, so that empty objects are kept as "{}"
            $body = json_encode(json_decode($body, false, 512, \JSON_THROW_ON_ERROR), \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_PRESERVE_ZERO_FRACTION | \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
        }

        return $command . "\n" . $body;
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
