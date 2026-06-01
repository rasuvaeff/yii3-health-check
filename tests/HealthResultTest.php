<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3HealthCheck\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Rasuvaeff\Yii3HealthCheck\Exception\InvalidCheckNameException;
use Rasuvaeff\Yii3HealthCheck\HealthResult;
use Rasuvaeff\Yii3HealthCheck\HealthStatus;

#[CoversClass(HealthResult::class)]
final class HealthResultTest extends TestCase
{
    #[Test]
    public function passCreatesResultWithPassStatus(): void
    {
        $result = HealthResult::pass(name: 'app');

        $this->assertSame(HealthStatus::Pass, $result->status);
        $this->assertSame('app', $result->name);
        $this->assertSame('', $result->message);
    }

    #[Test]
    public function warnCreatesResultWithWarnStatus(): void
    {
        $result = HealthResult::warn(name: 'db', message: 'slow');

        $this->assertSame(HealthStatus::Warn, $result->status);
        $this->assertSame('slow', $result->message);
    }

    #[Test]
    public function failCreatesResultWithFailStatus(): void
    {
        $result = HealthResult::fail(name: 'redis', message: 'connection refused');

        $this->assertSame(HealthStatus::Fail, $result->status);
        $this->assertSame('connection refused', $result->message);
    }

    #[Test]
    public function withElapsedMsReturnsNewInstance(): void
    {
        $original = HealthResult::pass(name: 'app');
        $modified = $original->withElapsedMs(elapsedMs: 42.5);

        $this->assertSame(0.0, $original->elapsedMs);
        $this->assertSame(42.5, $modified->elapsedMs);
        $this->assertSame($original->name, $modified->name);
    }

    #[Test]
    public function withDataReturnsNewInstance(): void
    {
        $original = HealthResult::pass(name: 'app');
        $modified = $original->withData(data: ['key' => 'value']);

        $this->assertSame([], $original->data);
        $this->assertSame(['key' => 'value'], $modified->data);
    }

    #[Test]
    public function toArrayContainsAllFields(): void
    {
        $result = HealthResult::warn(name: 'db', message: 'slow')
            ->withElapsedMs(elapsedMs: 150.0)
            ->withData(data: ['latency' => 150]);

        $array = $result->toArray();

        $this->assertSame('db', $array['name']);
        $this->assertSame('warn', $array['status']);
        $this->assertSame('slow', $array['message']);
        $this->assertSame(150.0, $array['elapsedMs']);
        $this->assertSame(['latency' => 150], $array['data']);
    }

    #[Test]
    public function toArrayOmitsZeroElapsed(): void
    {
        $result = HealthResult::pass(name: 'app');
        $array = $result->toArray();

        $this->assertArrayNotHasKey('elapsedMs', $array);
    }

    #[Test]
    public function toArrayOmitsEmptyData(): void
    {
        $result = HealthResult::pass(name: 'app');
        $array = $result->toArray();

        $this->assertArrayNotHasKey('data', $array);
    }

    #[Test]
    public function throwsOnInvalidName(): void
    {
        $this->expectException(InvalidCheckNameException::class);

        HealthResult::pass(name: 'INVALID');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function validNameProvider(): array
    {
        return [
            'simple' => ['app'],
            'dotted' => ['app.database'],
            'hyphenated' => ['app-db'],
            'with underscore' => ['app_db'],
            'with numbers' => ['app2'],
        ];
    }

    #[DataProvider('validNameProvider')]
    #[Test]
    public function acceptsValidNames(string $name): void
    {
        $result = HealthResult::pass(name: $name);

        $this->assertSame($name, $result->name);
    }
}
