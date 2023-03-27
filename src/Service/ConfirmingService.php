<?php
declare(strict_types=1);
/**
 * The MIT License (MIT).
 *
 * Copyright (c) 2017-2023 Michael Dekker (https://github.com/firstred)
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy of this software and
 * associated documentation files (the "Software"), to deal in the Software without restriction,
 * including without limitation the rights to use, copy, modify, merge, publish, distribute,
 * sublicense, and/or sell copies of the Software, and to permit persons to whom the Software
 * is furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all copies or
 * substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT
 * NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM,
 * DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 *
 * @author    Michael Dekker <git@michaeldekker.nl>
 * @copyright 2017-2023 Michael Dekker
 * @license   https://opensource.org/licenses/MIT The MIT License
 */

namespace Firstred\PostNL\Service;

use DateInterval;
use DateTimeInterface;
use Firstred\PostNL\Entity\Request\Confirming;
use Firstred\PostNL\Entity\Response\ConfirmingResponseShipment;
use Firstred\PostNL\Enum\PostNLApiMode;
use Firstred\PostNL\Exception\CifDownException;
use Firstred\PostNL\Exception\CifException;
use Firstred\PostNL\Exception\HttpClientException;
use Firstred\PostNL\Exception\NotFoundException;
use Firstred\PostNL\Exception\ResponseException;
use Firstred\PostNL\HttpClient\HttpClientInterface;
use Firstred\PostNL\Service\RequestBuilder\ConfirmingServiceRequestBuilderInterface;
use Firstred\PostNL\Service\RequestBuilder\Rest\ConfirmingServiceRestRequestBuilder;
use Firstred\PostNL\Service\RequestBuilder\Soap\ConfirmingServiceSoapRequestBuilder;
use Firstred\PostNL\Service\ResponseProcessor\ConfirmingServiceResponseProcessorInterface;
use Firstred\PostNL\Service\ResponseProcessor\ResponseProcessorSettersTrait;
use Firstred\PostNL\Service\ResponseProcessor\Rest\ConfirmingServiceRestResponseProcessor;
use Firstred\PostNL\Service\ResponseProcessor\Soap\ConfirmingServiceSoapResponseProcessor;
use ParagonIE\HiddenString\HiddenString;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * @since 2.0.0
 * @internal
 */
class ConfirmingService extends AbstractService implements ConfirmingServiceInterface
{
    // SOAP API specific
    public const SERVICES_NAMESPACE = 'http://postnl.nl/cif/services/ConfirmingWebService/';
    public const DOMAIN_NAMESPACE = 'http://postnl.nl/cif/domain/ConfirmingWebService/';

    use ResponseProcessorSettersTrait;

    protected ConfirmingServiceRequestBuilderInterface $requestBuilder;
    protected ConfirmingServiceResponseProcessorInterface $responseProcessor;

    /**
     * @param HiddenString                            $apiKey
     * @param PostNLApiMode                           $apiMode
     * @param bool                                    $sandbox
     * @param HttpClientInterface                     $httpClient
     * @param RequestFactoryInterface                 $requestFactory
     * @param StreamFactoryInterface                  $streamFactory
     * @param string                                  $version
     * @param CacheItemPoolInterface|null             $cache
     * @param DateInterval|DateTimeInterface|int|null $ttl
     */
    public function __construct(
        HiddenString                       $apiKey,
        PostNLApiMode                      $apiMode,
        bool                               $sandbox,
        HttpClientInterface                $httpClient,
        RequestFactoryInterface            $requestFactory,
        StreamFactoryInterface             $streamFactory,
        string                             $version = ConfirmingServiceInterface::DEFAULT_VERSION,
        CacheItemPoolInterface             $cache = null,
        DateInterval|DateTimeInterface|int $ttl = null
    ) {
        parent::__construct(
            apiKey: $apiKey,
            apiMode: $apiMode,
            sandbox: $sandbox,
            httpClient: $httpClient,
            requestFactory: $requestFactory,
            streamFactory: $streamFactory,
            version: $version,
            cache: $cache,
            ttl: $ttl,
        );
    }

    /**
     * Confirm a single shipment via REST.
     *
     * @throws CifDownException
     * @throws CifException
     * @throws ResponseException
     * @throws HttpClientException
     * @throws NotFoundException
     *
     * @since 1.0.0
     */
    public function confirmShipment(Confirming $confirming): ConfirmingResponseShipment
    {
        $response = $this->getHttpClient()->doRequest(request: $this->requestBuilder->buildConfirmRequest(confirming: $confirming));
        $objects = $this->responseProcessor->processConfirmResponse(response: $response);

        if (!empty($objects) && $objects[0] instanceof ConfirmingResponseShipment) {
            return $objects[0];
        }

        if (200 === $response->getStatusCode()) {
            throw new ResponseException(message: 'Invalid API Response', response: $response);
        }

        throw new NotFoundException(message: 'Unable to confirm');
    }

    /**
     * Confirm multiple shipments.
     *
     * @param Confirming[]                      $confirms ['uuid' => Confirming, ...]
     *
     * @phpstan-param array<string, Confirming> $confirms
     *
     * @return ConfirmingResponseShipment[]
     * @phpstan-return array<string, ConfirmingResponseShipment>
     *
     * @throws CifDownException
     * @throws CifException
     * @throws HttpClientException
     * @throws ResponseException
     *
     * @since 1.0.0
     */
    public function confirmShipments(array $confirms): array
    {
        $httpClient = $this->getHttpClient();

        foreach ($confirms as $confirm) {
            $httpClient->addOrUpdateRequest(
                id: $confirm->getId(),
                request: $this->requestBuilder->buildConfirmRequest(confirming: $confirm),
            );
        }

        $confirmingResponses = [];
        foreach ($httpClient->doRequests() as $uuid => $response) {
            $confirmingResponse = null;
            $objects = $this->responseProcessor->processConfirmResponse(response: $response);
            foreach ($objects as $object) {
                if (!$object instanceof ConfirmingResponseShipment) {
                    throw new ResponseException(
                        message: 'Invalid API Response',
                        code: $response->getStatusCode(),
                        previous: null,
                        response: $response,
                    );
                }

                $confirmingResponse = $object;
            }

            $confirmingResponses[$uuid] = $confirmingResponse;
        }

        return $confirmingResponses;
    }

    /**
     * @since 2.0.0
     */
    public function setAPIMode(PostNLApiMode $mode): void
    {
        if (PostNLApiMode::Rest === $mode) {
            $this->requestBuilder = new ConfirmingServiceRestRequestBuilder(
                apiKey: $this->getApiKey(),
                sandbox: $this->isSandbox(),
                requestFactory: $this->getRequestFactory(),
                streamFactory: $this->getStreamFactory(),
                version: $this->getVersion(),
            );
            $this->responseProcessor = new ConfirmingServiceRestResponseProcessor(
                apiKey: $this->getApiKey(),
                sandbox: $this->isSandbox(),
                requestFactory: $this->getRequestFactory(),
                streamFactory: $this->getStreamFactory(),
                version: $this->getVersion(),
            );
        } else {
            $this->requestBuilder = new ConfirmingServiceSoapRequestBuilder(
                apiKey: $this->getApiKey(),
                sandbox: $this->isSandbox(),
                requestFactory: $this->getRequestFactory(),
                streamFactory: $this->getStreamFactory(),
                version: $this->getVersion(),
            );
            $this->responseProcessor = new ConfirmingServiceSoapResponseProcessor(
                apiKey: $this->getApiKey(),
                sandbox: $this->isSandbox(),
                requestFactory: $this->getRequestFactory(),
                streamFactory: $this->getStreamFactory(),
                version: $this->getVersion(),
            );
        }
    }
}
