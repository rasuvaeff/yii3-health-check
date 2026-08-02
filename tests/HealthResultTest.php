<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3HealthCheck\Tests;

use Rasuvaeff\Yii3HealthCheck\Exception\InvalidCheckNameException;
use Rasuvaeff\Yii3HealthCheck\HealthResult;
use Rasuvaeff\Yii3HealthCheck\HealthStatus;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Expect;
use Testo\Test;

#[Test]
#[Covers(HealthResult::class)]
#[Covers(InvalidCheckNameException::class)]
final class HealthResultTest
{
    public function passCreatesResultWithPassStatus(): void
    {
        $result = HealthResult::pass(name: 'app');

        Assert::same($result->status, HealthStatus::Pass);
        Assert::same($result->name, 'app');
        Assert::same($result->message, '');
    }

    public function warnCreatesResultWithWarnStatus(): void
    {
        $result = HealthResult::warn(name: 'db', message: 'slow');

        Assert::same($result->status, HealthStatus::Warn);
        Assert::same($result->message, 'slow');
        Assert::same($result->elapsedMs, 0.0);
    }

    public function failCreatesResultWithFailStatus(): void
    {
        $result = HealthResult::fail(name: 'redis', message: 'connection refused');

        Assert::same($result->status, HealthStatus::Fail);
        Assert::same($result->message, 'connection refused');
        Assert::same($result->elapsedMs, 0.0);
    }

    public function passAcceptsData(): void
    {
        $result = HealthResult::pass(name: 'db', data: ['latencyMs' => 2.1]);

        Assert::same($result->data, ['latencyMs' => 2.1]);
    }

    public function failAcceptsData(): void
    {
        $result = HealthResult::fail(name: 'db', message: 'down', data: ['errno' => 111]);

        Assert::same($result->data, ['errno' => 111]);
    }

    public function withElapsedMsReturnsNewInstance(): void
    {
        $original = HealthResult::pass(name: 'app');
        $modified = $original->withElapsedMs(elapsedMs: 42.5);

        Assert::same($original->elapsedMs, 0.0);
        Assert::same($modified->elapsedMs, 42.5);
        Assert::same($modified->name, $original->name);
    }

    public function withDataReturnsNewInstance(): void
    {
        $original = HealthResult::pass(name: 'app');
        $modified = $original->withData(data: ['key' => 'value']);

        Assert::same($original->data, []);
        Assert::same($modified->data, ['key' => 'value']);
    }

    public function toArrayContainsAllFields(): void
    {
        $result = HealthResult::warn(name: 'db', message: 'slow')
            ->withElapsedMs(elapsedMs: 150.0)
            ->withData(data: ['latency' => 150]);

        $array = $result->toArray();

        Assert::same($array['name'], 'db');
        Assert::same($array['status'], 'warn');
        Assert::same($array['message'], 'slow');
        Assert::same($array['elapsedMs'], 150.0);
        Assert::same($array['data'], ['latency' => 150]);
    }

    public function toArrayOmitsZeroElapsed(): void
    {
        $result = HealthResult::pass(name: 'app');
        $array = $result->toArray();

        Assert::array($array)->doesNotHaveKeys('elapsedMs');
    }

    public function toArrayOmitsEmptyData(): void
    {
        $result = HealthResult::pass(name: 'app');
        $array = $result->toArray();

        Assert::array($array)->doesNotHaveKeys('data');
    }

    public function throwsOnInvalidName(): void
    {
        Expect::exception(InvalidCheckNameException::class);

        HealthResult::pass(name: 'INVALID');
    }

    public function throwsOnNameWithTrailingNewline(): void
    {
        Expect::exception(InvalidCheckNameException::class);

        HealthResult::pass(name: "app\n");
    }

    public static function validNameProvider(): iterable
    {
        yield 'simple' => ['app'];
        yield 'dotted' => ['app.database'];
        yield 'hyphenated' => ['app-db'];
        yield 'with underscore' => ['app_db'];
        yield 'with numbers' => ['app2'];
    }

    #[DataProvider('validNameProvider')]
    public function acceptsValidNames(string $name): void
    {
        $result = HealthResult::pass(name: $name);

        Assert::same($result->name, $name);
    }
}
