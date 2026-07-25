<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3HealthCheck\Tests;

use Rasuvaeff\Yii3HealthCheck\CallbackHealthCheck;
use Rasuvaeff\Yii3HealthCheck\Exception\InvalidCheckNameException;
use Rasuvaeff\Yii3HealthCheck\HealthResult;
use Rasuvaeff\Yii3HealthCheck\HealthStatus;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Expect;
use Testo\Test;

#[Test]
#[Covers(CallbackHealthCheck::class)]
final class CallbackHealthCheckTest
{
    public function returnsConfiguredName(): void
    {
        $check = new CallbackHealthCheck(
            name: 'app',
            check: static fn(): HealthResult => HealthResult::pass(name: 'app'),
        );

        Assert::same($check->name(), 'app');
    }

    public function executesCallbackAndReturnsResult(): void
    {
        $expected = HealthResult::pass(name: 'app');
        $check = new CallbackHealthCheck(
            name: 'app',
            check: static fn(): HealthResult => $expected,
        );

        Assert::same($check->check(), $expected);
    }

    public function callbackReturnsDifferentStatuses(): void
    {
        $check = new CallbackHealthCheck(
            name: 'db',
            check: static fn(): HealthResult => HealthResult::fail(name: 'db', message: 'down'),
        );

        $result = $check->check();

        Assert::same($result->status, HealthStatus::Fail);
    }

    public static function invalidNameProvider(): iterable
    {
        yield 'uppercase' => ['APP'];
        yield 'starts with digit' => ['1app'];
        yield 'spaces' => ['app name'];
        yield 'empty' => [''];
        yield 'special chars' => ['app@db'];
        yield 'trailing newline' => ["app\n"];
    }

    #[DataProvider('invalidNameProvider')]
    public function throwsOnInvalidName(string $name): void
    {
        Expect::exception(InvalidCheckNameException::class);

        new CallbackHealthCheck(
            name: $name,
            check: static fn(): HealthResult => HealthResult::pass(name: 'x'),
        );
    }

    public static function validNameProvider(): iterable
    {
        yield 'simple' => ['app'];
        yield 'dotted' => ['app.database'];
        yield 'hyphenated' => ['app-db'];
        yield 'underscore' => ['app_db'];
        yield 'with numbers' => ['app2'];
    }

    #[DataProvider('validNameProvider')]
    public function acceptsValidNames(string $name): void
    {
        $check = new CallbackHealthCheck(
            name: $name,
            check: static fn(): HealthResult => HealthResult::pass(name: $name),
        );

        Assert::same($check->name(), $name);
    }
}
