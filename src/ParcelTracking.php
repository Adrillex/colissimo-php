<?php

namespace DansMaCulotte\Colissimo;

use DansMaCulotte\Colissimo\Exceptions\Exception;
use GuzzleHttp\Client as HttpClient;

/**
 * Implementation of Parcel Tracking Web Service
 * https://developer.laposte.fr/products/suivi/latest
 */
class ParcelTracking
{
    /** @var string */
    const SERVICE_URL = 'https://api.laposte.fr/suivi/v2/idships/';

    private $apiKey;

    /**
     * Construct Method
     *
     * @param array $credentials Contains login and password for authentication
     * @param array $options Guzzle client options
     */
    public function __construct(string $apiKey, array $options = [])
    {
        $this->apiKey = $apiKey;

        $this->httpClient = new HttpClient(array_merge([
            'base_uri' => self::SERVICE_URL,
        ], $options));
    }

    /**
     * Retrieve up to 10 parcel status using IDs
     *
     * @param string $id Colissimo parcel number
     * @param array $options Additional parameters
     *
     * @return ParcelStatus
     * @throws Exception
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getStatusByID(array $ids, array $options = [], $lang = "fr_FR")
    {
        $result = $this->httpClient->request('GET', implode(',', $ids), [
            'query' => ["lang" => $lang],
            'headers' => [
                'Accept' => 'application/json',
                'X-Okapi-Key' => $this->apiKey
            ]
        ]);

        return (string) $result->getBody();
    }
}
