<?php

/*
 * This file is part of PHP OpenTSDB HTTP API Client.
 *
 * (c) Anton Komarev <anton@komarev.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Cog\OpenTsdbClient;

use Cog\OpenTsdbClient\Exception\OpenTsdbConnectionException;
use Cog\OpenTsdbClient\Exception\OpenTsdbException;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseInterface;

final class OpenTsdbClient
{
    public function __construct(
        private ClientInterface $httpClient,
        private string $baseUri,
    ) {}

    /**
     * @param list<DataPoint> $dataPointList
     */
    public function sendDataPointList(
        array $dataPointList,
    ): void {
        $result = $this->sendRequest(
            $this->buildUrl('/api/put'),
            json_encode($dataPointList),
        );

        $statusCode = $result->getStatusCode();

        switch ($statusCode) {
            case 200:
                // The request completed successfully
                break;
            case 204:
                // The server has completed the request successfully but is not returning content in the body.
                // This is primarily used for storing data points as it is not necessary to return data to caller.
                break;
            case 301:
                throw new OpenTsdbException(
                    'This may be used in the event that an API call has migrated or should be forwarded to another server.',
                    1,
                );
            case 400:
                throw new OpenTsdbException(
                    'Information provided by the API user, via a query string or content data, was in error or missing. This will usually include information in the error body about what parameter caused the issue. Correct the data and try again.',
                    2,
                );
            case 404:
                throw new OpenTsdbException(
                    'The requested endpoint or file was not found. This is usually related to the static file endpoint.',
                    3,
                );
            case 405:
                throw new OpenTsdbException(
                    'The requested verb or method was not allowed. Please see the documentation for the endpoint you are attempting to access.',
                    4,
                );
            case 406:
                throw new OpenTsdbException(
                    'The request could not generate a response in the format specified. For example, if you ask for a PNG file of the logs endpoing, you will get a 406 response since log entries cannot be converted to a PNG image (easily).',
                    5,
                );
            case 408:
                throw new OpenTsdbException(
                    'The request has timed out. This may be due to a timeout fetching data from the underlying storage system or other issues.',
                    6,
                );
            case 413:
                throw new OpenTsdbException(
                    'The results returned from a query may be too large for the server’s buffers to handle. This can happen if you request a lot of raw data from OpenTSDB. In such cases break your query up into smaller queries and run each individually.',
                    7,
                );
            case 500:
                throw new OpenTsdbException(
                    'An internal error occured within OpenTSDB. Make sure all of the systems OpenTSDB depends on are accessible and check the bug list for issues.',
                    8,
                );
            case 501:
                throw new OpenTsdbException(
                    'The requested feature has not been implemented yet. This may appear with formatters or when calling methods that depend on plugins.',
                    9,
                );
            case 503:
                throw new OpenTsdbException(
                    'A temporary overload has occurred. Check with other users/applications that are interacting with OpenTSDB and determine if you need to reduce requests or scale your system.',
                    10,
                );
            default:
                throw new OpenTsdbException(
                    "Failed to send metric to OpenTSDB. Unknown error. HTTP status code `$statusCode`.",
                    11,
                );
        }
    }

    /**
     * @param list<DataPoint> $dataPointList
     */
    public function sendDataPointListWithDebug(
        array $dataPointList,
    ): SendDataPointListResponse {
        $result = $this->sendRequest(
            $this->buildUrl('/api/put?details&summary'),
            json_encode($dataPointList),
        );

        $statusCode = $result->getStatusCode();
        $decodedResult = json_decode($result->getBody()->getContents(), true);

        return new SendDataPointListResponse(
            $statusCode,
            $decodedResult['success'],
            $decodedResult['failed'],
            $decodedResult['errors'],
        );
    }

    private function sendRequest(
        string $url,
        string $body,
    ): ResponseInterface {
        $request = new Request(
            'POST',
            $url,
            [
                'Content-Type' => 'application/json',
            ],
            $body,
        );

        try {
            return $this->httpClient->sendRequest($request);
        } catch (ClientExceptionInterface $exception) {
            throw new OpenTsdbConnectionException(
                'Failed to send data points to OpenTSDB.',
                0,
                $exception,
            );
        }
    }

    private function buildUrl(
        string $uri,
    ): string {
        return rtrim($this->baseUri, '/') . $uri;
    }
}
