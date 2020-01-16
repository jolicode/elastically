<?php

namespace JoliCode\Elastically\Transport;

use Elastica\Connection;
use Elastica\Exception\Connection\HttpException;
use Elastica\Exception\PartialShardFailureException;
use Elastica\Exception\ResponseException;
use Elastica\Request;
use Elastica\Response;
use Elastica\Transport\AbstractTransport;
use Elastica\Util;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpClient\Exception\ServerException;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Implement Symfony HttpClient as an Elastica Transport.
 */
class HttpClientTransport extends AbstractTransport
{
    private $client;

    /**
     * Elastica Connection does not have this option.
     */
    private $scheme;

    public function __construct(HttpClientInterface $client, string $scheme = 'http', ?Connection $connection = null)
    {
        parent::__construct($connection);

        $this->client = $client;
        $this->scheme = $scheme;
    }

    public function exec(Request $request, array $params): Response
    {
        $connection = $this->getConnection();

        $headers = $connection->hasConfig('headers') && \is_array($connection->getConfig('headers'))
            ? $connection->getConfig('headers')
            : [];
        $headers['Content-Type'] = $request->getContentType();

        $options = [
            'headers' => $headers,
            'json' => $request->getData(),
        ];

        if ($connection->getTimeout()) {
            $options['timeout'] = $connection->getTimeout();
        }

        $proxy = $connection->getProxy();
        if (!\is_null($proxy)) {
            $options['proxy'] = $proxy;
        }

        try {
            $response = $this->client->request($request->getMethod(), $this->_getUri($request, $connection), $options);
            $elasticaResponse = new Response($response->getContent(), $response->getStatusCode());
        } catch (ClientException | ServerException $e) {
            $elasticaResponse = new Response($response->getContent(false), $response->getStatusCode());
            throw new ResponseException($request, $elasticaResponse);
        } catch (HttpExceptionInterface $e) {
            throw new HttpException($e->getCode(), $request);
        } catch (TransportExceptionInterface $e) {
            throw new HttpException($e->getMessage(), $request);
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

    protected function _getUri(Request $request, Connection $connection): string
    {
        $url = $connection->hasConfig('url') ? $connection->getConfig('url') : '';

        if (!empty($url)) {
            $baseUri = $url;
        } else {
            $baseUri = $this->scheme.'://'.$connection->getHost().':'.$connection->getPort().'/'.$connection->getPath();
        }

        $requestPath = $request->getPath();
        if (!Util::isDateMathEscaped($requestPath)) {
            $requestPath = Util::escapeDateMath($requestPath);
        }

        $baseUri .= $requestPath;

        $query = $request->getQuery();

        if (!empty($query)) {
            $baseUri .= '?'.\http_build_query($this->sanityzeQueryStringBool($query));
        }

        return $baseUri;
    }
}
