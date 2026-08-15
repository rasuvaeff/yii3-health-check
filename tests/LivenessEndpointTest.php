<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3HealthCheck\Tests;

use Rasuvaeff\Yii3HealthCheck\LivenessEndpoint;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

#[Test]
#[Covers(LivenessEndpoint::class)]
final class LivenessEndpointTest
{
    private FakeResponseFactory $responseFactory;

    #[BeforeTest]
    public function setUp(): void
    {
        $this->responseFactory = new FakeResponseFactory();
    }

    public function returns200WithPassStatus(): void
    {
        $endpoint = new LivenessEndpoint(responseFactory: $this->responseFactory);

        $response = $endpoint->handle(new FakeRequest());

        Assert::same($response->getStatusCode(), 200);

        /** @var FakeResponse $response */
        $body = json_decode($response->getBodyContents(), associative: true, depth: 512, flags: JSON_THROW_ON_ERROR);
        assert(is_array($body));
        Assert::same($body['status'], 'pass');
    }

    public function returnsDefaultAliveMessage(): void
    {
        $endpoint = new LivenessEndpoint(responseFactory: $this->responseFactory);

        $response = $endpoint->handle(new FakeRequest());

        /** @var FakeResponse $response */
        $body = json_decode($response->getBodyContents(), associative: true, depth: 512, flags: JSON_THROW_ON_ERROR);
        assert(is_array($body));
        Assert::same($body['message'], 'alive');
    }

    public function returnsCustomMessage(): void
    {
        $endpoint = new LivenessEndpoint(
            responseFactory: $this->responseFactory,
            statusMessage: 'ok',
        );

        $response = $endpoint->handle(new FakeRequest());

        /** @var FakeResponse $response */
        $body = json_decode($response->getBodyContents(), associative: true, depth: 512, flags: JSON_THROW_ON_ERROR);
        assert(is_array($body));
        Assert::same($body['message'], 'ok');
    }

    public function setsJsonContentTypeHeader(): void
    {
        $endpoint = new LivenessEndpoint(responseFactory: $this->responseFactory);

        $response = $endpoint->handle(new FakeRequest());

        Assert::same($response->getHeaderLine('content-type'), 'application/json');
    }
}
