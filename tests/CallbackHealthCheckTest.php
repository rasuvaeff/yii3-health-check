<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3HealthCheck\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Rasuvaeff\Yii3HealthCheck\CallbackHealthCheck;
use Rasuvaeff\Yii3HealthCheck\Exception\InvalidCheckNameException;
use Rasuvaeff\Yii3HealthCheck\HealthResult;
use Rasuvaeff\Yii3HealthCheck\HealthStatus;

#[CoversClass(CallbackHealthCheck::class)]
final class CallbackHealthCheckTest extends TestCase
{
    #[Test]
    public function returnsConfiguredName(): void
    {
        $check = new CallbackHealthCheck(
            name: 'app',
            check: static fn(): HealthResult => HealthResult::pass(name: 'app'),
        );

        $this->assertSame('app', $check->name());
    }

    #[Test]
    public function executesCallbackAndReturnsResult(): void
    {
        $expected = HealthResult::pass(name: 'app');
        $check = new CallbackHealthCheck(
            name: 'app',
            check: static fn(): HealthResult => $expected,
        );

        $this->assertSame($expected, $check->check());
    }

    #[Test]
    public function callbackReturnsDifferentStatuses(): void
    {
        $check = new CallbackHealthCheck(
            name: 'db',
            check: static fn(): HealthResult => HealthResult::fail(name: 'db', message: 'down'),
        );

        $result = $check->check();

        $this->assertSame(HealthStatus::Fail, $result->status);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidNameProvider(): array
    {
        return [
            'uppercase' => ['APP'],
            'starts with digit' => ['1app'],
            'spaces' => ['app name'],
            'empty' => [''],
            'special chars' => ['app@db'],
        ];
    }

    #[DataProvider('invalidNameProvider')]
    #[Test]
    public function throwsOnInvalidName(string $name): void
    {
        $this->expectException(InvalidCheckNameException::class);

        new CallbackHealthCheck(
            name: $name,
            check: static fn(): HealthResult => HealthResult::pass(name: 'x'),
        );
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
            'underscore' => ['app_db'],
            'with numbers' => ['app2'],
        ];
    }

    #[DataProvider('validNameProvider')]
    #[Test]
    public function acceptsValidNames(string $name): void
    {
        $check = new CallbackHealthCheck(
            name: $name,
            check: static fn(): HealthResult => HealthResult::pass(name: $name),
        );

        $this->assertSame($name, $check->name());
    }
}
