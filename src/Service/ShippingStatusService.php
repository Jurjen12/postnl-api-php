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
use Firstred\PostNL\Entity\Request\CompleteStatus;
use Firstred\PostNL\Entity\Request\CompleteStatusByReference;
use Firstred\PostNL\Entity\Request\CurrentStatus;
use Firstred\PostNL\Entity\Request\CurrentStatusByReference;
use Firstred\PostNL\Entity\Request\GetSignature;
use Firstred\PostNL\Entity\Response\CompleteStatusResponse;
use Firstred\PostNL\Entity\Response\CurrentStatusResponse;
use Firstred\PostNL\Entity\Response\GetSignatureResponseSignature;
use Firstred\PostNL\Enum\PostNLApiMode;
use Firstred\PostNL\Exception\DeserializationException;
use Firstred\PostNL\Exception\HttpClientException;
use Firstred\PostNL\Exception\InvalidArgumentException as PostNLInvalidArgumentException;
use Firstred\PostNL\Exception\NotFoundException;
use Firstred\PostNL\Exception\NotSupportedException;
use Firstred\PostNL\Exception\ResponseException;
use Firstred\PostNL\HttpClient\HttpClientInterface;
use Firstred\PostNL\Service\RequestBuilder\Rest\ShippingStatusServiceRestRequestBuilder;
use Firstred\PostNL\Service\ResponseProcessor\ResponseProcessorSettersTrait;
use Firstred\PostNL\Service\ResponseProcessor\Rest\ShippingStatusServiceRestResponseProcessor;
use Firstred\PostNL\Service\ResponseProcessor\ShippingStatusServiceResponseProcessorInterface;
use GuzzleHttp\Psr7\Message as PsrMessage;
use InvalidArgumentException;
use ParagonIE\HiddenString\HiddenString;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException as PsrCacheInvalidArgumentException;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * @since 2.0.0
 * @internal
 */
class ShippingStatusService extends AbstractService implements ShippingStatusServiceInterface
{
    use ResponseProcessorSettersTrait;

    protected ShippingStatusServiceRestRequestBuilder $requestBuilder;
    protected ShippingStatusServiceResponseProcessorInterface $responseProcessor;

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
        string                             $version = ShippingStatusServiceInterface::DEFAULT_VERSION,
        CacheItemPoolInterface             $cache = null,
        DateInterval|DateTimeInterface|int $ttl = null,
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
     * Gets the current status.
     *
     * This is a combi-function, supporting the following:
     * - CurrentStatus (by barcode):
     *   - Fill the Shipment->Barcode property. Leave the rest empty.
     * - CurrentStatusByReference:
     *   - Fill the Shipment->Reference property. Leave the rest empty.
     * - CurrentStatusByPhase:
     *   - Fill the Shipment->PhaseCode property, do not pass Barcode or Reference.
     *     Optionally add DateFrom and/or DateTo.
     * - CurrentStatusByStatus:
     *   - Fill the Shipment->StatusCode property. Leave the rest empty.
     *
     * @param CurrentStatus|CurrentStatusByReference $currentStatus
     *
     * @return CurrentStatusResponse
     *
     * @throws DeserializationException
     * @throws HttpClientException
     * @throws NotFoundException
     * @throws NotSupportedException
     * @throws PostNLInvalidArgumentException
     * @throws PsrCacheInvalidArgumentException
     * @throws ResponseException
     * @since 1.0.0
     */
    public function currentStatus(CurrentStatusByReference|CurrentStatus $currentStatus): CurrentStatusResponse
    {
        $item = $this->retrieveCachedItem(uuid: $currentStatus->getId());
        $response = null;
        if ($item instanceof CacheItemInterface && $item->isHit()) {
            $response = $item->get();
            try {
                $response = PsrMessage::parseResponse(message: $response);
            } catch (InvalidArgumentException $e) {
            }
        }
        if (!$response instanceof ResponseInterface) {
            $response = $this->getHttpClient()->doRequest(request: $this->requestBuilder->buildCurrentStatusRequest(currentStatus: $currentStatus));
        }

        $object = $this->responseProcessor->processCurrentStatusResponse(response: $response);
        if ($object instanceof CurrentStatusResponse) {
            if ($item instanceof CacheItemInterface
                && $response instanceof ResponseInterface
                && 200 === $response->getStatusCode()
            ) {
                $item->set(value: PsrMessage::toString(message: $response));
                $this->cacheItem(item: $item);
            }

            return $object;
        }

        throw new NotFoundException(message: 'Unable to retrieve current status');
    }

    /**
     * Get current statuses REST.
     *
     * @param CurrentStatus[] $currentStatuses
     *
     * @return CurrentStatusResponse[]
     *
     * @throws HttpClientException
     * @throws NotSupportedException
     * @throws PostNLInvalidArgumentException
     * @throws PsrCacheInvalidArgumentException
     * @throws ResponseException
     * @throws DeserializationException
     * @since 1.2.0
     */
    public function currentStatuses(array $currentStatuses): array
    {
        $httpClient = $this->getHttpClient();

        $responses = [];
        foreach ($currentStatuses as $index => $currentStatus) {
            $item = $this->retrieveCachedItem(uuid: $index);
            $response = null;
            if ($item instanceof CacheItemInterface && $item->isHit()) {
                $response = $item->get();
                $response = PsrMessage::parseResponse(message: $response);
                $responses[$index] = $response;
            }

            $httpClient->addOrUpdateRequest(
                id: $index,
                request: $this->requestBuilder->buildCurrentStatusRequest(currentStatus: $currentStatus)
            );
        }

        $newResponses = $httpClient->doRequests();
        foreach ($newResponses as $uuid => $newResponse) {
            if ($newResponse instanceof ResponseInterface
                && 200 === $newResponse->getStatusCode()
            ) {
                $item = $this->retrieveCachedItem(uuid: $uuid);
                if ($item instanceof CacheItemInterface) {
                    $item->set(value: PsrMessage::toString(message: $newResponse));
                    $this->getCache()->saveDeferred(item: $item);
                }
            }
        }
        if ($this->getCache() instanceof CacheItemPoolInterface) {
            $this->getCache()->commit();
        }

        $currentStatusResponses = [];
        foreach ($responses + $newResponses as $uuid => $response) {
            $currentStatusResponses[$uuid] = $this->responseProcessor->processCurrentStatusResponse(response: $response);
        }

        return $currentStatusResponses;
    }

    /**
     * Gets the complete status.
     *
     * This is a combi-function, supporting the following:
     * - CurrentStatus (by barcode):
     *   - Fill the Shipment->Barcode property. Leave the rest empty.
     * - CurrentStatusByReference:
     *   - Fill the Shipment->Reference property. Leave the rest empty.
     *
     * @throws HttpClientException
     * @throws NotSupportedException
     * @throws PostNLInvalidArgumentException
     * @throws ResponseException
     * @throws NotFoundException
     *
     * @since 1.0.0
     */
    public function completeStatus(CompleteStatusByReference|CompleteStatus $completeStatus): CompleteStatusResponse
    {
        try {
            $item = $this->retrieveCachedItem(uuid: $completeStatus->getId());
        } catch (PsrCacheInvalidArgumentException) {
            $item = null;
        }
        $response = null;
        if ($item instanceof CacheItemInterface && $item->isHit()) {
            $response = $item->get();
            try {
                $response = PsrMessage::parseResponse(message: $response);
            } catch (InvalidArgumentException) {
            }
        }
        if (!$response instanceof ResponseInterface) {
            $response = $this->getHttpClient()->doRequest(request: $this->requestBuilder->buildCompleteStatusRequest(completeStatus: $completeStatus));
        }

        $object = $this->responseProcessor->processCompleteStatusResponse(response: $response);
        if ($object instanceof CompleteStatusResponse) {
            if ($item instanceof CacheItemInterface
                && $response instanceof ResponseInterface
                && 200 === $response->getStatusCode()
            ) {
                $item->set(value: PsrMessage::toString(message: $response));
                $this->cacheItem(item: $item);
            }

            return $object;
        }

        throw new NotFoundException(message: 'Unable to retrieve complete status');
    }

    /**
     * Get complete statuses REST.
     *
     * @param CompleteStatus[]|CompleteStatusByReference[] $completeStatuses
     *
     * @return CompleteStatusResponse[]
     *
     * @throws HttpClientException
     * @throws NotSupportedException
     * @throws PostNLInvalidArgumentException
     * @throws PsrCacheInvalidArgumentException
     * @throws ResponseException
     *
     * @since 1.2.0
     */
    public function completeStatuses(array $completeStatuses): array
    {
        $httpClient = $this->getHttpClient();

        $responses = [];
        foreach ($completeStatuses as $index => $completeStatus) {
            $item = $this->retrieveCachedItem(uuid: $index);
            $response = null;
            if ($item instanceof CacheItemInterface && $item->isHit()) {
                $response = $item->get();
                $response = PsrMessage::parseResponse(message: $response);
                $responses[$index] = $response;
            }

            $httpClient->addOrUpdateRequest(
                id: $index,
                request: $this->requestBuilder->buildCompleteStatusRequest(completeStatus: $completeStatus)
            );
        }

        $newResponses = $httpClient->doRequests();
        foreach ($newResponses as $uuid => $newResponse) {
            if ($newResponse instanceof ResponseInterface
                && 200 === $newResponse->getStatusCode()
            ) {
                $item = $this->retrieveCachedItem(uuid: $uuid);
                if ($item instanceof CacheItemInterface) {
                    $item->set(value: PsrMessage::toString(message: $newResponse));
                    $this->getCache()->saveDeferred(item: $item);
                }
            }
        }
        if ($this->getCache() instanceof CacheItemPoolInterface) {
            $this->getCache()->commit();
        }

        $completeStatusResponses = [];
        foreach ($responses + $newResponses as $uuid => $response) {
            $completeStatusResponses[$uuid] = $this->responseProcessor->processCompleteStatusResponse(response: $response);
        }

        return $completeStatusResponses;
    }

    /**
     * Gets the signature.
     *
     * @throws ResponseException
     * @throws PsrCacheInvalidArgumentException
     * @throws HttpClientException
     * @throws NotSupportedException
     * @throws PostNLInvalidArgumentException
     * @throws NotFoundException
     *
     * @since 1.0.0
     */
    public function getSignature(GetSignature $getSignature): GetSignatureResponseSignature
    {
        $item = $this->retrieveCachedItem(uuid: $getSignature->getId());
        $response = null;
        if ($item instanceof CacheItemInterface && $item->isHit()) {
            $response = $item->get();
            try {
                $response = PsrMessage::parseResponse(message: $response);
            } catch (InvalidArgumentException) {
            }
        }
        if (!$response instanceof ResponseInterface) {
            $response = $this->getHttpClient()->doRequest(request: $this->requestBuilder->buildGetSignatureRequest(getSignature: $getSignature));
        }

        $object = $this->responseProcessor->processGetSignatureResponse(response: $response);
        if ($object instanceof GetSignatureResponseSignature) {
            if ($item instanceof CacheItemInterface
                && $response instanceof ResponseInterface
                && 200 === $response->getStatusCode()
            ) {
                $item->set(value: PsrMessage::toString(message: $response));
                $this->cacheItem(item: $item);
            }

            return $object;
        }

        throw new NotFoundException(message: 'Unable to get signature');
    }

    /**
     * Get multiple signatures.
     *
     * @param GetSignature[] $getSignatures
     *
     * @return GetSignatureResponseSignature[]
     *
     * @throws HttpClientException
     * @throws NotSupportedException
     * @throws PostNLInvalidArgumentException
     * @throws PsrCacheInvalidArgumentException
     * @throws ResponseException
     *
     * @since 1.2.0
     */
    public function getSignatures(array $getSignatures): array
    {
        $httpClient = $this->getHttpClient();

        $responses = [];
        foreach ($getSignatures as $index => $getsignature) {
            $item = $this->retrieveCachedItem(uuid: $index);
            $response = null;
            if ($item instanceof CacheItemInterface && $item->isHit()) {
                $response = $item->get();
                $response = PsrMessage::parseResponse(message: $response);
                $responses[$index] = $response;
            }

            $httpClient->addOrUpdateRequest(
                id: $index,
                request: $this->requestBuilder->buildGetSignatureRequest(getSignature: $getsignature)
            );
        }

        $newResponses = $httpClient->doRequests();
        foreach ($newResponses as $uuid => $newResponse) {
            if ($newResponse instanceof ResponseInterface
                && 200 === $newResponse->getStatusCode()
            ) {
                $item = $this->retrieveCachedItem(uuid: $uuid);
                if ($item instanceof CacheItemInterface) {
                    $item->set(value: PsrMessage::toString(message: $newResponse));
                    $this->getCache()->saveDeferred(item: $item);
                }
            }
        }
        if ($this->getCache() instanceof CacheItemPoolInterface) {
            $this->getCache()->commit();
        }

        $signatureResponses = [];
        foreach ($responses + $newResponses as $uuid => $response) {
            $signatureResponses[$uuid] = $this->responseProcessor->processGetSignatureResponse(response: $response);
        }

        return $signatureResponses;
    }

    /**
     * @since 2.0.0
     */
    public function setAPIMode(PostNLApiMode $mode): void
    {
        $this->requestBuilder = new ShippingStatusServiceRestRequestBuilder(
            apiKey: $this->getApiKey(),
            sandbox: $this->isSandbox(),
            requestFactory: $this->getRequestFactory(),
            streamFactory: $this->getStreamFactory(),
            version: $this->getVersion(),
        );
        $this->responseProcessor = new ShippingStatusServiceRestResponseProcessor(
            apiKey: $this->getApiKey(),
            sandbox: $this->isSandbox(),
            requestFactory: $this->getRequestFactory(),
            streamFactory: $this->getStreamFactory(),
            version: $this->getVersion(),
        );
    }
}
