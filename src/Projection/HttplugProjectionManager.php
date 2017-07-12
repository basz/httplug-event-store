<?php
/**
 * This file is part of the prooph/httplug-event-store.
 * (c) 2017-2017 prooph software GmbH <contact@prooph.de>
 * (c) 2017-2017 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventStore\Httplug\Projection;

use Http\Client\HttpClient;
use Http\Discovery\MessageFactoryDiscovery;
use Http\Message\RequestFactory;
use Prooph\EventStore\Exception\ProjectionNotFound;
use Prooph\EventStore\Exception\RuntimeException;
use Prooph\EventStore\Projection\ProjectionManager;
use Prooph\EventStore\Projection\ProjectionStatus;
use Prooph\EventStore\Projection\Projector;
use Prooph\EventStore\Projection\Query;
use Prooph\EventStore\Projection\ReadModel;
use Prooph\EventStore\Projection\ReadModelProjector;
use Psr\Http\Message\UriInterface;

final class HttplugProjectionManager implements ProjectionManager
{
    /**
     * @var HttpClient
     */
    private $httpClient;

    /**
     * @var UriInterface
     */
    private $uri;

    /**
     * @var RequestFactory
     */
    private $requestFactory;

    public function __construct(
        HttpClient $httpClient,
        UriInterface $uri,
        RequestFactory $requestFactory = null
    ) {
        $this->httpClient = $httpClient;
        $this->uri = $uri;
        $this->requestFactory = $requestFactory ?: MessageFactoryDiscovery::find();
    }

    public function createQuery(): Query
    {
        throw new \BadMethodCallException(__METHOD__ . ' not implemented');
    }

    public function createProjection(
        string $name,
        array $options = []
    ): Projector {
        throw new \BadMethodCallException(__METHOD__ . ' not implemented');
    }

    public function createReadModelProjection(
        string $name,
        ReadModel $readModel,
        array $options = []
    ): ReadModelProjector {
        throw new \BadMethodCallException(__METHOD__ . ' not implemented');
    }

    public function deleteProjection(string $name, bool $deleteEmittedEvents): void
    {
        if ($deleteEmittedEvents) {
            $deleteEmittedEvents = 'true';
        } else {
            $deleteEmittedEvents = 'false';
        }

        $request = $this->requestFactory->createRequest(
            'POST',
            $this->uri->withPath('projection/delete/' . urlencode($name) . '/' . $deleteEmittedEvents)
        );

        $response = $this->httpClient->sendRequest($request);

        switch ($response->getStatusCode()) {
            case 404:
                throw ProjectionNotFound::withName($name);
            case 204:
                break;
            default:
                throw new RuntimeException('Unknown error occurred');
        }
    }

    public function resetProjection(string $name): void
    {
        $request = $this->requestFactory->createRequest(
            'POST',
            $this->uri->withPath('projection/reset/' . urlencode($name))
        );

        $response = $this->httpClient->sendRequest($request);

        switch ($response->getStatusCode()) {
            case 404:
                throw ProjectionNotFound::withName($name);
            case 204:
                break;
            default:
                throw new RuntimeException('Unknown error occurred');
        }
    }

    public function stopProjection(string $name): void
    {
        $request = $this->requestFactory->createRequest(
            'POST',
            $this->uri->withPath('projection/stop/' . urlencode($name))
        );

        $response = $this->httpClient->sendRequest($request);

        switch ($response->getStatusCode()) {
            case 404:
                throw ProjectionNotFound::withName($name);
            case 204:
                break;
            default:
                throw new RuntimeException('Unknown error occurred');
        }
    }

    public function fetchProjectionNames(?string $filter, int $limit = 20, int $offset = 0): array
    {
        $query = 'limit=' . $limit . '&offset=' . $offset;

        if (null !== $filter) {
            $path = '/projections/' . urlencode($filter);
        } else {
            $path = '/projections';
        }

        $request = $this->requestFactory->createRequest(
            'GET',
            $this->uri->withPath($path)->withQuery($query),
            [
                'Accept' => 'application/json',
            ]
        );

        $response = $this->httpClient->sendRequest($request);

        if ($response->getStatusCode() !== 200) {
            throw new RuntimeException('Unknown error occurred');
        }

        $projectionNames = json_decode($response->getBody()->getContents(), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Could not json decode response');
        }

        return $projectionNames;
    }

    public function fetchProjectionNamesRegex(string $regex, int $limit = 20, int $offset = 0): array
    {
        $query = 'limit=' . $limit . '&offset=' . $offset;

        $path = 'projections-regex/' . urlencode($regex);

        $request = $this->requestFactory->createRequest(
            'GET',
            $this->uri->withPath($path)->withQuery($query),
            [
                'Accept' => 'application/json',
            ]
        );

        $response = $this->httpClient->sendRequest($request);

        if ($response->getStatusCode() !== 200) {
            throw new RuntimeException('Unknown error occurred');
        }

        $projectionNames = json_decode($response->getBody()->getContents(), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Could not json decode response');
        }

        return $projectionNames;
    }

    public function fetchProjectionStatus(string $name): ProjectionStatus
    {
        $request = $this->requestFactory->createRequest(
            'GET',
            $this->uri->withPath('projection/status/' . urlencode($name)),
            [
                'Accept' => 'application/json',
            ]
        );

        $response = $this->httpClient->sendRequest($request);

        switch ($response->getStatusCode()) {
            case 404:
                throw ProjectionNotFound::withName($name);
            case 200:
                return ProjectionStatus::byName($response->getReasonPhrase());
            default:
                throw new RuntimeException('Unknown error occurred');
        }
    }

    public function fetchProjectionStreamPositions(string $name): array
    {
        $request = $this->requestFactory->createRequest(
            'GET',
            $this->uri->withPath('projection/stream-positions/' . urlencode($name)),
            [
                'Accept' => 'application/json',
            ]
        );

        $response = $this->httpClient->sendRequest($request);

        switch ($response->getStatusCode()) {
            case 404:
                throw ProjectionNotFound::withName($name);
            case 200:
                $streamPositions = json_decode($response->getBody()->getContents(), true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new RuntimeException('Could not json decode response');
                }

                return $streamPositions;
            default:
                throw new RuntimeException('Unknown error occurred');
        }
    }

    public function fetchProjectionState(string $name): array
    {
        $request = $this->requestFactory->createRequest(
            'GET',
            $this->uri->withPath('projection/state/' . urlencode($name)),
            [
                'Accept' => 'application/json',
            ]
        );

        $response = $this->httpClient->sendRequest($request);

        switch ($response->getStatusCode()) {
            case 404:
                throw ProjectionNotFound::withName($name);
            case 200:
                $state = json_decode($response->getBody()->getContents(), true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new RuntimeException('Could not json decode response');
                }

                return $state;
            default:
                throw new RuntimeException('Unknown error occurred');
        }
    }
}