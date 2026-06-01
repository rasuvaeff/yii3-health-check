<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3HealthCheck;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * @api
 */
final class HealthEndpoint implements RequestHandlerInterface
{
    public function __construct(
        private readonly HealthChecker $checker,
        private readonly ResponseFactoryInterface $responseFactory,
    ) {}

    #[\Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $results = $this->checker->run();
        $status = HealthChecker::aggregateStatus($results);

        return $this->createResponse($results, $status);
    }

    /**
     * @param array<string, HealthResult> $results
     */
    private function createResponse(array $results, HealthStatus $status): ResponseInterface
    {
        $httpStatus = $status === HealthStatus::Fail ? 503 : 200;

        $body = [
            'status' => $status->value,
            'checks' => array_map(
                static fn(HealthResult $r): array => $r->toArray(),
                $results,
            ),
        ];

        $response = $this->responseFactory->createResponse($httpStatus);
        $response->getBody()->write(json_encode($body, JSON_THROW_ON_ERROR));

        return $response->withHeader(name: 'Content-Type', value: 'application/json');
    }
}
