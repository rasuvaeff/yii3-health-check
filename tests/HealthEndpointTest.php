<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3HealthCheck\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Rasuvaeff\Yii3HealthCheck\CallbackHealthCheck;
use Rasuvaeff\Yii3HealthCheck\HealthChecker;
use Rasuvaeff\Yii3HealthCheck\HealthEndpoint;
use Rasuvaeff\Yii3HealthCheck\HealthResult;

#[CoversClass(HealthEndpoint::class)]
final class HealthEndpointTest extends TestCase
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
        $checker = new HealthChecker(checks: [
            new CallbackHealthCheck(name: 'app', check: static fn(): HealthResult => HealthResult::pass(name: 'app')),
        ]);
        $endpoint = new HealthEndpoint(checker: $checker, responseFactory: $this->responseFactory);

        $response = $endpoint->handle(new FakeRequest());

        $this->assertSame(200, $response->getStatusCode());

        /** @var FakeResponse $response */
        $body = json_decode($response->getBodyContents(), true, 512, JSON_THROW_ON_ERROR);
        assert(is_array($body));
        $this->assertSame('pass', $body['status']);
        $this->assertArrayHasKey('checks', $body);
    }

    #[Test]
    public function returns503WhenAnyCheckFails(): void
    {
        $checker = new HealthChecker(checks: [
            new CallbackHealthCheck(name: 'app', check: static fn(): HealthResult => HealthResult::pass(name: 'app')),
            new CallbackHealthCheck(name: 'db', check: static fn(): HealthResult => HealthResult::fail(name: 'db', message: 'down')),
        ]);
        $endpoint = new HealthEndpoint(checker: $checker, responseFactory: $this->responseFactory);

        $response = $endpoint->handle(new FakeRequest());

        $this->assertSame(503, $response->getStatusCode());

        /** @var FakeResponse $response */
        $body = json_decode($response->getBodyContents(), true, 512, JSON_THROW_ON_ERROR);
        assert(is_array($body));
        $this->assertSame('fail', $body['status']);
    }

    #[Test]
    public function returns200WithWarnStatus(): void
    {
        $checker = new HealthChecker(checks: [
            new CallbackHealthCheck(name: 'app', check: static fn(): HealthResult => HealthResult::warn(name: 'app', message: 'slow')),
        ]);
        $endpoint = new HealthEndpoint(checker: $checker, responseFactory: $this->responseFactory);

        $response = $endpoint->handle(new FakeRequest());

        $this->assertSame(200, $response->getStatusCode());

        /** @var FakeResponse $response */
        $body = json_decode($response->getBodyContents(), true, 512, JSON_THROW_ON_ERROR);
        assert(is_array($body));
        $this->assertSame('warn', $body['status']);
    }

    #[Test]
    public function setsJsonContentTypeHeader(): void
    {
        $checker = new HealthChecker();
        $endpoint = new HealthEndpoint(checker: $checker, responseFactory: $this->responseFactory);

        $response = $endpoint->handle(new FakeRequest());

        $this->assertSame('application/json', $response->getHeaderLine('content-type'));
    }

    #[Test]
    public function includesAllChecksInResponse(): void
    {
        $checker = new HealthChecker(checks: [
            new CallbackHealthCheck(name: 'app', check: static fn(): HealthResult => HealthResult::pass(name: 'app')),
            new CallbackHealthCheck(name: 'db', check: static fn(): HealthResult => HealthResult::pass(name: 'db')),
        ]);
        $endpoint = new HealthEndpoint(checker: $checker, responseFactory: $this->responseFactory);

        $response = $endpoint->handle(new FakeRequest());

        /** @var FakeResponse $response */
        $body = json_decode($response->getBodyContents(), true, 512, JSON_THROW_ON_ERROR);
        assert(is_array($body));

        $this->assertCount(2, $body['checks']);
    }

    #[Test]
    public function eachCheckContainsNameAndStatus(): void
    {
        $checker = new HealthChecker(checks: [
            new CallbackHealthCheck(name: 'redis', check: static fn(): HealthResult => HealthResult::fail(name: 'redis', message: 'refused')),
        ]);
        $endpoint = new HealthEndpoint(checker: $checker, responseFactory: $this->responseFactory);

        $response = $endpoint->handle(new FakeRequest());

        /** @var FakeResponse $response */
        $body = json_decode($response->getBodyContents(), true, 512, JSON_THROW_ON_ERROR);
        assert(is_array($body));
        $checks = $body['checks'];
        assert(is_array($checks));

        $this->assertSame('redis', $checks['redis']['name']);
        $this->assertSame('fail', $checks['redis']['status']);
        $this->assertSame('refused', $checks['redis']['message']);
        $this->assertArrayNotHasKey('data', $checks['redis']);
    }
}
