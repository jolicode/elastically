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

use Elastic\Elasticsearch\Response\Elasticsearch;
use JoliCode\Elastically\Client;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Records every request sent to Elasticsearch, for the Symfony profiler.
 *
 * @phpstan-type Trace array{
 *     method: string,
 *     url: string,
 *     request_body: string,
 *     status_code: int|null,
 *     response_body: string|null,
 *     duration: float,
 *     error: string|null,
 * }
 */
final class TraceableClient extends Client implements ResetInterface
{
    /** @var list<Trace> */
    private array $traces = [];
    private ?Stopwatch $stopwatch = null;

    public function setStopwatch(?Stopwatch $stopwatch): void
    {
        $this->stopwatch = $stopwatch;
    }

    public function sendRequest(RequestInterface $request): Elasticsearch
    {
        $event = $this->stopwatch?->start(\sprintf('%s %s', $request->getMethod(), $request->getUri()->getPath()), 'elastically');
        $start = microtime(true);

        try {
            $response = parent::sendRequest($request);
        } catch (\Throwable $e) { // @phpstan-ignore catch.neverThrown (the parent method does not document its exceptions)
            $event?->stop();
            $this->addTrace($request, $start, $e);

            throw $e;
        }

        $event?->stop();
        $this->addTrace($request, $start, $response);

        return $response;
    }

    /**
     * @return list<Trace>
     */
    public function getTraces(): array
    {
        return $this->traces;
    }

    public function reset(): void
    {
        $this->traces = [];
    }

    private function addTrace(RequestInterface $request, float $start, ResponseInterface|\Throwable $result): void
    {
        $response = $result instanceof ResponseInterface ? $result : null;
        if ($result instanceof \Throwable && method_exists($result, 'getResponse') && ($exceptionResponse = $result->getResponse()) instanceof ResponseInterface) {
            $response = $exceptionResponse;
        }

        // The host is only known once the transport picked a node
        $uri = $request->getUri();
        $sentRequest = $this->getTransport()->getLastRequest();
        if ($sentRequest && $sentRequest->getUri()->getPath() === $uri->getPath()) {
            $uri = $sentRequest->getUri();
        }

        $this->traces[] = [
            'method' => $request->getMethod(),
            'url' => (string) $uri->withUserInfo(''),
            'request_body' => (string) $request->getBody(),
            'status_code' => $response?->getStatusCode(),
            'response_body' => $response ? (string) $response->getBody() : null,
            'duration' => (microtime(true) - $start) * 1000,
            'error' => $result instanceof \Throwable ? $result->getMessage() : null,
        ];
    }
}
