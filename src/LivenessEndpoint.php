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
final readonly class LivenessEndpoint implements RequestHandlerInterface
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private string $statusMessage = 'alive',
    ) {}

    #[\Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $body = [
            'status' => HealthStatus::Pass->value,
            'message' => $this->statusMessage,
        ];

        $response = $this->responseFactory->createResponse(200);
        $response->getBody()->write(json_encode($body, JSON_THROW_ON_ERROR));

        return $response->withHeader(name: 'Content-Type', value: 'application/json');
    }
}
