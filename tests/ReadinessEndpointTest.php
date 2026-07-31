<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3HealthCheck\Tests;

use Rasuvaeff\Yii3HealthCheck\CallbackHealthCheck;
use Rasuvaeff\Yii3HealthCheck\ChecksJsonResponse;
use Rasuvaeff\Yii3HealthCheck\HealthChecker;
use Rasuvaeff\Yii3HealthCheck\HealthResult;
use Rasuvaeff\Yii3HealthCheck\ReadinessEndpoint;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

#[Test]
#[Covers(ReadinessEndpoint::class)]
#[Covers(ChecksJsonResponse::class)]
final class ReadinessEndpointTest
{
    private FakeResponseFactory $responseFactory;

    #[BeforeTest]
    public function setUp(): void
    {
        $this->responseFactory = new FakeResponseFactory();
    }

    public function returns200WhenReady(): void
    {
        $checker = new HealthChecker(checks: [
            new CallbackHealthCheck(name: 'db', check: static fn(): HealthResult => HealthResult::pass(name: 'db')),
        ]);
        $endpoint = new ReadinessEndpoint(checker: $checker, responseFactory: $this->responseFactory);

        $response = $endpoint->handle(new FakeRequest());

        Assert::same($response->getStatusCode(), 200);
    }

    public function returns503WhenNotReady(): void
    {
        $checker = new HealthChecker(checks: [
            new CallbackHealthCheck(name: 'db', check: static fn(): HealthResult => HealthResult::fail(name: 'db', message: 'down')),
        ]);
        $endpoint = new ReadinessEndpoint(checker: $checker, responseFactory: $this->responseFactory);

        $response = $endpoint->handle(new FakeRequest());

        Assert::same($response->getStatusCode(), 503);
    }

    public function responseBodyContainsStatusAndChecks(): void
    {
        $checker = new HealthChecker(checks: [
            new CallbackHealthCheck(name: 'db', check: static fn(): HealthResult => HealthResult::pass(name: 'db')),
        ]);
        $endpoint = new ReadinessEndpoint(checker: $checker, responseFactory: $this->responseFactory);

        $response = $endpoint->handle(new FakeRequest());

        /** @var FakeResponse $response */
        $body = json_decode($response->getBodyContents(), true, 512, JSON_THROW_ON_ERROR);
        assert(is_array($body));
        Assert::same($body['status'], 'pass');
        Assert::array($body)->hasKeys('checks');
        Assert::count($body['checks'], 1);
    }

    public function responseBodyContainsCheckDetails(): void
    {
        $checker = new HealthChecker(checks: [
            new CallbackHealthCheck(name: 'db', check: static fn(): HealthResult => HealthResult::fail(name: 'db', message: 'timeout')),
        ]);
        $endpoint = new ReadinessEndpoint(checker: $checker, responseFactory: $this->responseFactory);

        $response = $endpoint->handle(new FakeRequest());

        Assert::same($response->getStatusCode(), 503);

        /** @var FakeResponse $response */
        $body = json_decode($response->getBodyContents(), true, 512, JSON_THROW_ON_ERROR);
        assert(is_array($body));
        Assert::same($body['status'], 'fail');
        $checks = $body['checks'];
        assert(is_array($checks));
        Assert::same($checks['db']['name'], 'db');
        Assert::same($checks['db']['status'], 'fail');
        Assert::same($checks['db']['message'], 'timeout');
        Assert::array($checks['db'])->doesNotHaveKeys('data');
    }

    public function setsJsonContentTypeHeader(): void
    {
        $checker = new HealthChecker();
        $endpoint = new ReadinessEndpoint(checker: $checker, responseFactory: $this->responseFactory);

        $response = $endpoint->handle(new FakeRequest());

        Assert::same($response->getHeaderLine('content-type'), 'application/json');
    }
}
