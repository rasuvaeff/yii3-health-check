<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3HealthCheck\Tests;

use Rasuvaeff\Yii3HealthCheck\CallbackHealthCheck;
use Rasuvaeff\Yii3HealthCheck\ChecksJsonResponse;
use Rasuvaeff\Yii3HealthCheck\HealthChecker;
use Rasuvaeff\Yii3HealthCheck\HealthEndpoint;
use Rasuvaeff\Yii3HealthCheck\HealthResult;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

#[Test]
#[Covers(HealthEndpoint::class)]
#[Covers(ChecksJsonResponse::class)]
final class HealthEndpointTest
{
    private FakeResponseFactory $responseFactory;

    #[BeforeTest]
    public function setUp(): void
    {
        $this->responseFactory = new FakeResponseFactory();
    }

    public function returns200WithPassStatus(): void
    {
        $checker = new HealthChecker(checks: [
            new CallbackHealthCheck(name: 'app', check: static fn(): HealthResult => HealthResult::pass(name: 'app')),
        ]);
        $endpoint = new HealthEndpoint(checker: $checker, responseFactory: $this->responseFactory);

        $response = $endpoint->handle(new FakeRequest());

        Assert::same($response->getStatusCode(), 200);

        /** @var FakeResponse $response */
        $body = json_decode($response->getBodyContents(), associative: true, depth: 512, flags: JSON_THROW_ON_ERROR);
        assert(is_array($body));
        Assert::same($body['status'], 'pass');
        Assert::array($body)->hasKeys('checks');
    }

    public function returns503WhenAnyCheckFails(): void
    {
        $checker = new HealthChecker(checks: [
            new CallbackHealthCheck(name: 'app', check: static fn(): HealthResult => HealthResult::pass(name: 'app')),
            new CallbackHealthCheck(name: 'db', check: static fn(): HealthResult => HealthResult::fail(name: 'db', message: 'down')),
        ]);
        $endpoint = new HealthEndpoint(checker: $checker, responseFactory: $this->responseFactory);

        $response = $endpoint->handle(new FakeRequest());

        Assert::same($response->getStatusCode(), 503);

        /** @var FakeResponse $response */
        $body = json_decode($response->getBodyContents(), associative: true, depth: 512, flags: JSON_THROW_ON_ERROR);
        assert(is_array($body));
        Assert::same($body['status'], 'fail');
    }

    public function returns200WithWarnStatus(): void
    {
        $checker = new HealthChecker(checks: [
            new CallbackHealthCheck(name: 'app', check: static fn(): HealthResult => HealthResult::warn(name: 'app', message: 'slow')),
        ]);
        $endpoint = new HealthEndpoint(checker: $checker, responseFactory: $this->responseFactory);

        $response = $endpoint->handle(new FakeRequest());

        Assert::same($response->getStatusCode(), 200);

        /** @var FakeResponse $response */
        $body = json_decode($response->getBodyContents(), associative: true, depth: 512, flags: JSON_THROW_ON_ERROR);
        assert(is_array($body));
        Assert::same($body['status'], 'warn');
    }

    public function setsJsonContentTypeHeader(): void
    {
        $checker = new HealthChecker();
        $endpoint = new HealthEndpoint(checker: $checker, responseFactory: $this->responseFactory);

        $response = $endpoint->handle(new FakeRequest());

        Assert::same($response->getHeaderLine('content-type'), 'application/json');
    }

    public function includesAllChecksInResponse(): void
    {
        $checker = new HealthChecker(checks: [
            new CallbackHealthCheck(name: 'app', check: static fn(): HealthResult => HealthResult::pass(name: 'app')),
            new CallbackHealthCheck(name: 'db', check: static fn(): HealthResult => HealthResult::pass(name: 'db')),
        ]);
        $endpoint = new HealthEndpoint(checker: $checker, responseFactory: $this->responseFactory);

        $response = $endpoint->handle(new FakeRequest());

        /** @var FakeResponse $response */
        $body = json_decode($response->getBodyContents(), associative: true, depth: 512, flags: JSON_THROW_ON_ERROR);
        assert(is_array($body));

        Assert::count($body['checks'], 2);
    }

    public function eachCheckContainsNameAndStatus(): void
    {
        $checker = new HealthChecker(checks: [
            new CallbackHealthCheck(name: 'redis', check: static fn(): HealthResult => HealthResult::fail(name: 'redis', message: 'refused')),
        ]);
        $endpoint = new HealthEndpoint(checker: $checker, responseFactory: $this->responseFactory);

        $response = $endpoint->handle(new FakeRequest());

        /** @var FakeResponse $response */
        $body = json_decode($response->getBodyContents(), associative: true, depth: 512, flags: JSON_THROW_ON_ERROR);
        assert(is_array($body));
        $checks = $body['checks'];
        assert(is_array($checks));

        Assert::same($checks['redis']['name'], 'redis');
        Assert::same($checks['redis']['status'], 'fail');
        Assert::same($checks['redis']['message'], 'refused');
        Assert::array($checks['redis'])->doesNotHaveKeys('data');
    }
}
