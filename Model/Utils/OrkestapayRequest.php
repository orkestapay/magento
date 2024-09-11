<?php

/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Orkestapay\Cards\Model\Utils;

use GuzzleHttp\Client;
use GuzzleHttp\ClientFactory;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ResponseFactory;
use Magento\Framework\Webapi\Rest\Request;

class OrkestapayRequest
{

    protected $logger;

    /**
     * @var ResponseFactory
     */
    private $responseFactory;
    /**
     * @var ClientFactory
     */
    private $clientFactory;


    /**
     *
     * @param  $logger_interface
     * @param ClientFactory $clientFactory
     * @param ResponseFactory $responseFactory
     */
    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        ClientFactory $clientFactory,
        ResponseFactory $responseFactory
    ) {
        $this->logger = $logger;
        $this->clientFactory = $clientFactory;
        $this->responseFactory = $responseFactory;
    }

    public function get_access_token($credentials, $is_sandbox)
    {
        $prod_url =  'https://api.orkestapay.com';
        $sandbox_url = 'https://api.sand.orkestapay.com';
        $base_uri = $is_sandbox ? $sandbox_url : $prod_url;

        $this->logger->debug('get_access_token', $credentials);
        $this->logger->debug('base_uri', ['$base_uri ' => $base_uri]);

        /** @var Client $client */
        $client = $this->clientFactory->create(['config' => [
            'base_uri' => $base_uri,
            'headers' => ['Content-Type' => 'application/json']
        ]]);

        try {
            $response = $client->request('POST', '/v1/oauth/tokens', ['json' => $credentials]);
            $responseBody = $response->getBody();
            $responseContent = $responseBody->getContents();

            return json_decode($responseContent, true);
        } catch (GuzzleException $exception) {
            /** @var Response $response */
            return [
                'status' => $exception->getCode(),
                'reason' => $exception->getMessage()
            ];
        }
    }

    public function make($path, $is_sandbox, $auth, $method = 'GET', $data = null, $idempotency_key = null)
    {
        $prod_url =  'https://api.orkestapay.com';
        $sandbox_url = 'https://api.sand.orkestapay.com';
        $base_uri = $is_sandbox ? $sandbox_url : $prod_url;

        $access_token = $this->get_access_token($auth, $is_sandbox);
        $this->logger->debug('Access Token: ', $access_token);

        /** @var Client $client */
        $client = $this->clientFactory->create(['config' => [
            'base_uri' => $base_uri,
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $access_token['access_token']
            ]
        ]]);

        try {
            if ($method === 'POST' || $method === 'PUT') {
                $response = $client->request($method, $path, ['json' => $data, 'headers' => ['Idempotency-Key' => $idempotency_key]]);
            } else {
                $response = $client->request($method, $path, ['query' => $data]);
            }

            $responseBody = $response->getBody();
            $responseContent = $responseBody->getContents();

            return json_decode($responseContent, true);
        } catch (GuzzleException $exception) {
            /** @var Response $response */
            return [
                'status' => $exception->getCode(),
                'reason' => $exception->getMessage()
            ];
        }
    }
}
