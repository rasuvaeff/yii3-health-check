<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3HealthCheck\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Rasuvaeff\Yii3HealthCheck\CallbackHealthCheck;
use Rasuvaeff\Yii3HealthCheck\HealthChecker;
use Rasuvaeff\Yii3HealthCheck\HealthResult;
use Rasuvaeff\Yii3HealthCheck\ReadinessEndpoint;

#[CoversClass(ReadinessEndpoint::class)]
final class ReadinessEndpointTest extends TestCase
{
    private FakeResponseFactory $responseFactory;

    #[\Override]
    protected function setUp(): void
    {
        $this->responseFactory = new FakeResponseFactory();
    }

    #[Test]
    public function returns200WhenReady(): void
    {
        $checker = new HealthChecker(checks: [
            new CallbackHealthCheck(name: 'db', check: static fn(): HealthResult => HealthResult::pass(name: 'db')),
        ]);
        $endpoint = new ReadinessEndpoint(checker: $checker, responseFactory: $this->responseFactory);

        $response = $endpoint->handle(new FakeRequest());

        $this->assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function returns503WhenNotReady(): void
    {
        $checker = new HealthChecker(checks: [
            new CallbackHealthCheck(name: 'db', check: static fn(): HealthResult => HealthResult::fail(name: 'db', message: 'down')),
        ]);
        $endpoint = new ReadinessEndpoint(checker: $checker, responseFactory: $this->responseFactory);

        $response = $endpoint->handle(new FakeRequest());

        $this->assertSame(503, $response->getStatusCode());
    }

    #[Test]
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
        $this->assertSame('pass', $body['status']);
        $this->assertArrayHasKey('checks', $body);
        $this->assertCount(1, $body['checks']);
    }

    #[Test]
    public function responseBodyContainsCheckDetails(): void
    {
        $checker = new HealthChecker(checks: [
            new CallbackHealthCheck(name: 'db', check: static fn(): HealthResult => HealthResult::fail(name: 'db', message: 'timeout')),
        ]);
        $endpoint = new ReadinessEndpoint(checker: $checker, responseFactory: $this->responseFactory);

        $response = $endpoint->handle(new FakeRequest());

        $this->assertSame(503, $response->getStatusCode());

        /** @var FakeResponse $response */
        $body = json_decode($response->getBodyContents(), true, 512, JSON_THROW_ON_ERROR);
        assert(is_array($body));
        $this->assertSame('fail', $body['status']);
        $checks = $body['checks'];
        assert(is_array($checks));
        $this->assertSame('db', $checks['db']['name']);
        $this->assertSame('fail', $checks['db']['status']);
        $this->assertSame('timeout', $checks['db']['message']);
        $this->assertArrayNotHasKey('data', $checks['db']);
    }

    #[Test]
    public function setsJsonContentTypeHeader(): void
    {
        $checker = new HealthChecker();
        $endpoint = new ReadinessEndpoint(checker: $checker, responseFactory: $this->responseFactory);

        $response = $endpoint->handle(new FakeRequest());

        $this->assertSame('application/json', $response->getHeaderLine('content-type'));
    }
}
