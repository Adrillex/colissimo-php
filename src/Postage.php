<?php

namespace DansMaCulotte\Colissimo;

use DansMaCulotte\Colissimo\Exceptions\Exception;
use DansMaCulotte\Colissimo\Resources\PickupPoint;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Response;

/**
 * Implementation of Postage Web Service
 * https://www.colissimo.entreprise.laposte.fr/system/files/imagescontent/docs/spec_ws_affranchissement.pdf
 */
class Postage extends Colissimo
{
    /** @var string */
    const SERVICE_URL = 'https://ws.colissimo.fr/sls-ws/SlsServiceWSRest/2.0/';

    /**
     * Construct Method
     *
     * @param array $credentials Contains accountNumber and password for authentication
     * @param array $options Guzzle Client options
     */
    public function __construct(array $credentials, array $options = [])
    {
        parent::__construct($credentials, self::SERVICE_URL, $options);

        $this->credentials = [
            'contractNumber' => $credentials['accountNumber'],
            'password' => $credentials['password']
        ];
    }

    /**
     * Generate postage label
     *
     * @param string $outputPrintingType
     * @param string $productCode
     * @param array $sender
     * @param array $addressee
     * @param string $weight
     * @param string $depositDate
     * @param array $options Additional parameters
     *
     * @return PickupPoint[]
     */
    public function generateLabel(
        string $outputPrintingType,
        string $productCode,
        array $sender,
        array $addressee,
        string $weight,
        string $depositDate,
        array $options = []
    ) {
        $mandatory = [
            'outputFormat' => [
                'x' => 0,
                'y' => 0,
                'outputPrintingType' => $outputPrintingType
            ],
            'letter' => [
                'service' => [
                    'productCode' => $productCode,
                    'depositDate' => $depositDate
                ],
                'parcel' => [
                    'weight' => $weight
                ],
                'sender' => $sender,
                'addressee' => $addressee
            ],
        ];

        $params = $this->array_merge_recursive_distinct($mandatory, $options);

        try {
            $response = $this->httpClient->request('POST', 'generateLabel', [
                'json' => array_merge($params, $this->credentials)
            ]);
        } catch (RequestException $e) {
            $response = $this->parseMultipartResponse($e->getResponse());
            $infos = json_decode($response['jsonInfos']);

            throw Exception::requestError($infos->messages[0]->messageContent);
        }

        return $this->parseMultipartResponse($response);
    }

    /**
     * array_merge_recursive does indeed merge arrays, but it converts values with duplicate
     * keys to arrays rather than overwriting the value in the first array with the duplicate
     * value in the second array, as array_merge does. I.e., with array_merge_recursive,
     * this happens (documented behavior):
     *
     * array_merge_recursive(array('key' => 'org value'), array('key' => 'new value'));
     *     => array('key' => array('org value', 'new value'));
     *
     * array_merge_recursive_distinct does not change the datatypes of the values in the arrays.
     * Matching keys' values in the second array overwrite those in the first array, as is the
     * case with array_merge, i.e.:
     *
     * array_merge_recursive_distinct(array('key' => 'org value'), array('key' => 'new value'));
     *     => array('key' => array('new value'));
     *
     * Parameters are passed by reference, though only for performance reasons. They're not
     * altered by this function.
     *
     * @param array $array1
     * @param array $array2
     * @return array
     * @author Daniel <daniel (at) danielsmedegaardbuus (dot) dk>
     * @author Gabriel Sobrinho <gabriel (dot) sobrinho (at) gmail (dot) com>
     */
    function array_merge_recursive_distinct ( array &$array1, array &$array2 )
    {
        $merged = $array1;

        foreach ( $array2 as $key => &$value )
        {
            if ( is_array ( $value ) && isset ( $merged [$key] ) && is_array ( $merged [$key] ) )
            {
                $merged [$key] = self::array_merge_recursive_distinct ( $merged [$key], $value );
            }
            else
            {
                $merged [$key] = $value;
            }
        }

        return $merged;
    }

    function parseMultipartResponse(Response $response)
    {
        $boundary = $this->parseBoundaryFromHeader($response->getHeader('Content-Type')[0]);
        return $this->parseMultipartContent((string) $response->getBody()->getContents(), $boundary);
    }

    function parseBoundaryFromHeader($contentType)
    {
        // grab multipart boundary from content type header
        preg_match('/boundary="(.*)"/', $contentType, $matches);
        return $matches[1];
    }

    function parseMultipartContent(String $content, String $boundary)
    {
        // split content by boundary and get rid of last -- element
        $a_blocks = preg_split("/-+$boundary/",  $content);
        array_pop($a_blocks);

        // loop data blocks
        foreach ($a_blocks as $id => $block)
        {
            if (empty($block))
                continue;

            // Search for "Content-ID" string to extract id as 1st group regex, and data value as 2nd group regex
            preg_match("/Content-ID: <([^>]*)>[\n|\r]+([^\n\r].*)?$/s", $block, $matches);

            if (count($matches) === 3) {
                $parsed[$matches[1]] = $matches[2];
            }
        }

        return $parsed;
    }
}
