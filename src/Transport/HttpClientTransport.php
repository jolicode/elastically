<?php

/*
 * This file is part of the jolicode/elastically library.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JoliCode\Elastically\Transport;

use Elastica\Connection;
use Elastica\Exception\Connection\HttpException;
use Elastica\Exception\ConnectionException;
use Elastica\Exception\ExceptionInterface;
use Elastica\Exception\PartialShardFailureException;
use Elastica\Exception\ResponseException;
use Elastica\JSON;
use Elastica\Request;
use Elastica\Request as ElasticaRequest;
use Elastica\Response;
use Elastica\Transport\AbstractTransport;
use Elastica\Util;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpClient\Exception\ServerException;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Implement Symfony HttpClient as an Elastica Transport.
 */
class HttpClientTransport extends AbstractTransport
{
    private HttpClientInterface $client;

    /**
     * Elastica Connection does not have this option.
     */
    private string $scheme;

    /**
     * @throws ExceptionInterface
     * @throws TransportExceptionInterface
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     */
    public function exec(Request $request, array $params): Response
    {
        $connection = $this->getConnection();

        $headers = $connection->hasConfig('headers') && \is_array($connection->getConfig('headers'))
            ? $connection->getConfig('headers')
            : [];
        $headers['Content-Type'] = $request->getContentType();

        $options = [
            'headers' => $headers,
        ];

        $data = $request->getData();
        $method = $request->getMethod();
        if (!empty($data) || '0' === $data) {
            if (ElasticaRequest::GET === $method) {
                $method = ElasticaRequest::POST;
            }

            if (\is_array($data)) {
                $options['body'] = JSON::stringify($data, \JSON_UNESCAPED_UNICODE);
            } else {
                $options['body'] = $data;
            }
        }

        if ($connection->getTimeout()) {
            $options['timeout'] = $connection->getTimeout();
        }

        $proxy = $connection->getProxy();
        if (null !== $proxy) {
            $options['proxy'] = $proxy;
        }

        try {
            $response = $this->client->request($method, $this->_getUri($request, $connection), $options);
            $elasticaResponse = new Response($response->getContent(), $response->getStatusCode());
        } catch (ClientException|ServerException $e) { // Error 4xx and 5xx
            $elasticaResponse = new Response($response->getContent(false), $response->getStatusCode());
        } catch (HttpExceptionInterface $e) {
            throw new HttpException($e->getCode(), $request);
        } catch (TransportExceptionInterface $e) {
            throw new ConnectionException($e->getMessage(), $request);
        }

        if ($connection->hasConfig('bigintConversion')) {
            $elasticaResponse->setJsonBigintConversion($connection->getConfig('bigintConversion'));
        }

        $elasticaResponse->setTransferInfo($response->getInfo());

        if ($elasticaResponse->hasError()) {
            throw new ResponseException($request, $elasticaResponse);
        }

        if ($elasticaResponse->hasFailedShards()) {
            throw new PartialShardFailureException($request, $elasticaResponse);
        }

        return $elasticaResponse;
    }

    public function __construct(HttpClientInterface $client, string $scheme = 'http', Connection $connection = null)
    {
        parent::__construct($connection);

        $this->client = $client;
        $this->scheme = $scheme;
    }

    protected function _getUri(Request $request, Connection $connection): string
    {
        $url = $connection->hasConfig('url') ? $connection->getConfig('url') : '';

        if (!empty($url)) {
            $baseUri = $url;
        } else {
            $baseUri = $this->scheme . '://' . $connection->getHost() . ':' . $connection->getPort() . '/' . $connection->getPath();
        }

        $requestPath = $request->getPath();
        if (!Util::isDateMathEscaped($requestPath)) {
            $requestPath = Util::escapeDateMath($requestPath);
        }

        $baseUri .= $requestPath;

        $query = $request->getQuery();

        if (!empty($query)) {
            $baseUri .= '?' . http_build_query($this->sanityzeQueryStringBool($query));
        }

        return $baseUri;
    }
}
