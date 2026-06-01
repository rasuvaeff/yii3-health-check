<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3HealthCheck\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Rasuvaeff\Yii3HealthCheck\LivenessEndpoint;

#[CoversClass(LivenessEndpoint::class)]
final class LivenessEndpointTest extends TestCase
{
    private FakeResponseFactory $responseFactory;

    #[\Override]
    protected function setUp(): void
    {
        $this->responseFactory = new FakeResponseFactory();
    }

    #[Test]
    public function returns200WithPassStatus(): void
    {
        $endpoint = new LivenessEndpoint(responseFactory: $this->responseFactory);

        $response = $endpoint->handle(new FakeRequest());

        $this->assertSame(200, $response->getStatusCode());

        /** @var FakeResponse $response */
        $body = json_decode($response->getBodyContents(), true, 512, JSON_THROW_ON_ERROR);
        assert(is_array($body));
        $this->assertSame('pass', $body['status']);
    }

    #[Test]
    public function returnsDefaultAliveMessage(): void
    {
        $endpoint = new LivenessEndpoint(responseFactory: $this->responseFactory);

        $response = $endpoint->handle(new FakeRequest());

        /** @var FakeResponse $response */
        $body = json_decode($response->getBodyContents(), true, 512, JSON_THROW_ON_ERROR);
        assert(is_array($body));
        $this->assertSame('alive', $body['message']);
    }

    #[Test]
    public function returnsCustomMessage(): void
    {
        $endpoint = new LivenessEndpoint(
            responseFactory: $this->responseFactory,
            statusMessage: 'ok',
        );

        $response = $endpoint->handle(new FakeRequest());

        /** @var FakeResponse $response */
        $body = json_decode($response->getBodyContents(), true, 512, JSON_THROW_ON_ERROR);
        assert(is_array($body));
        $this->assertSame('ok', $body['message']);
    }

    #[Test]
    public function setsJsonContentTypeHeader(): void
    {
        $endpoint = new LivenessEndpoint(responseFactory: $this->responseFactory);

        $response = $endpoint->handle(new FakeRequest());

        $this->assertSame('application/json', $response->getHeaderLine('content-type'));
    }
}
