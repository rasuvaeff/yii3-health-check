<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3HealthCheck;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * @internal
 */
final readonly class ChecksJsonResponse
{
    /**
     * @param array<string, HealthResult> $results
     */
    public static function create(ResponseFactoryInterface $responseFactory, array $results): ResponseInterface
    {
        $status = HealthChecker::aggregateStatus($results);
        $httpStatus = $status === HealthStatus::Fail ? 503 : 200;

        $body = [
            'status' => $status->value,
            'checks' => array_map(
                static fn(HealthResult $r): array => $r->toArray(),
                $results,
            ),
        ];

        $response = $responseFactory->createResponse($httpStatus);
        $response->getBody()->write(json_encode($body, JSON_THROW_ON_ERROR));

        return $response->withHeader(name: 'Content-Type', value: 'application/json');
    }
}
