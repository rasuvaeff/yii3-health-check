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
final readonly class HealthEndpoint implements RequestHandlerInterface
{
    public function __construct(
        private HealthChecker $checker,
        private ResponseFactoryInterface $responseFactory,
    ) {}

    #[\Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return ChecksJsonResponse::create($this->responseFactory, $this->checker->run());
    }
}
