<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3HealthCheck\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Rasuvaeff\Yii3HealthCheck\CallbackHealthCheck;
use Rasuvaeff\Yii3HealthCheck\HealthChecker;
use Rasuvaeff\Yii3HealthCheck\HealthResult;
use Rasuvaeff\Yii3HealthCheck\HealthStatus;

#[CoversClass(HealthChecker::class)]
final class HealthCheckerTest extends TestCase
{
    #[Test]
    public function runReturnsResultsForAllChecks(): void
    {
        $checker = new HealthChecker(checks: [
            new CallbackHealthCheck(name: 'app', check: static fn(): HealthResult => HealthResult::pass(name: 'app')),
            new CallbackHealthCheck(name: 'db', check: static fn(): HealthResult => HealthResult::pass(name: 'db')),
        ]);

        $results = $checker->run();

        $this->assertCount(2, $results);
        $this->assertArrayHasKey('app', $results);
        $this->assertArrayHasKey('db', $results);
    }

    #[Test]
    public function runCatchesExceptionAndReturnsFail(): void
    {
        $checker = new HealthChecker(checks: [
            new CallbackHealthCheck(
                name: 'redis',
                check: static function (): HealthResult {
                    throw new \RuntimeException('Connection refused');
                },
            ),
        ]);

        $results = $checker->run();

        $this->assertSame(HealthStatus::Fail, $results['redis']->status);
        $this->assertSame('Connection refused', $results['redis']->message);
    }

    #[Test]
    public function aggregateStatusReturnsPassWhenAllPass(): void
    {
        $results = [
            'app' => HealthResult::pass(name: 'app'),
            'db' => HealthResult::pass(name: 'db'),
        ];

        $this->assertSame(HealthStatus::Pass, HealthChecker::aggregateStatus($results));
    }

    #[Test]
    public function aggregateStatusReturnsFailWhenAnyFails(): void
    {
        $results = [
            'app' => HealthResult::pass(name: 'app'),
            'db' => HealthResult::fail(name: 'db', message: 'down'),
        ];

        $this->assertSame(HealthStatus::Fail, HealthChecker::aggregateStatus($results));
    }

    #[Test]
    public function aggregateStatusReturnsWarnWhenAnyWarnsAndNoFails(): void
    {
        $results = [
            'app' => HealthResult::pass(name: 'app'),
            'db' => HealthResult::warn(name: 'db', message: 'slow'),
        ];

        $this->assertSame(HealthStatus::Warn, HealthChecker::aggregateStatus($results));
    }

    #[Test]
    public function aggregateStatusReturnsPassForEmptyResults(): void
    {
        $this->assertSame(HealthStatus::Pass, HealthChecker::aggregateStatus(results: []));
    }

    #[Test]
    public function addRegistersCheck(): void
    {
        $checker = new HealthChecker();
        $checker->add(new CallbackHealthCheck(
            name: 'app',
            check: static fn(): HealthResult => HealthResult::pass(name: 'app'),
        ));

        $results = $checker->run();

        $this->assertArrayHasKey('app', $results);
    }

    #[Test]
    public function runByNameReturnsSingleResult(): void
    {
        $checker = new HealthChecker(checks: [
            new CallbackHealthCheck(name: 'app', check: static fn(): HealthResult => HealthResult::pass(name: 'app')),
            new CallbackHealthCheck(name: 'db', check: static fn(): HealthResult => HealthResult::pass(name: 'db')),
        ]);

        $results = $checker->runByName(name: 'app');

        $this->assertCount(1, $results);
        $this->assertArrayHasKey('app', $results);
    }

    #[Test]
    public function runByNameReturnsEmptyForUnknown(): void
    {
        $checker = new HealthChecker();

        $results = $checker->runByName(name: 'unknown');

        $this->assertSame([], $results);
    }

    #[Test]
    public function hasReturnsTrueForRegisteredCheck(): void
    {
        $checker = new HealthChecker(checks: [
            new CallbackHealthCheck(name: 'app', check: static fn(): HealthResult => HealthResult::pass(name: 'app')),
        ]);

        $this->assertTrue($checker->has('app'));
        $this->assertFalse($checker->has('unknown'));
    }

    #[Test]
    public function runMeasuresElapsedTime(): void
    {
        $checker = new HealthChecker(checks: [
            new CallbackHealthCheck(name: 'app', check: static fn(): HealthResult => HealthResult::pass(name: 'app')),
        ]);

        $results = $checker->run();

        $this->assertGreaterThanOrEqual(0.0, $results['app']->elapsedMs);
    }

    #[Test]
    public function runReturnsWarnWhenCheckExceedsThreshold(): void
    {
        $callCount = 0;
        $checker = new HealthChecker(
            checks: [
                new CallbackHealthCheck(
                    name: 'slow',
                    check: function () use (&$callCount): HealthResult {
                        $callCount++;

                        if ($callCount === 1) {
                            usleep(10000);
                        }

                        return HealthResult::pass(name: 'slow');
                    },
                ),
            ],
            warnThresholdMs: 1.0,
        );

        $results = $checker->run();

        $this->assertSame(HealthStatus::Warn, $results['slow']->status);
    }

    #[Test]
    public function runPreservesExistingWarnStatus(): void
    {
        $checker = new HealthChecker(
            checks: [
                new CallbackHealthCheck(
                    name: 'app',
                    check: static fn(): HealthResult => HealthResult::warn(name: 'app', message: 'degraded'),
                ),
            ],
            warnThresholdMs: 999999.0,
        );

        $results = $checker->run();

        $this->assertSame(HealthStatus::Warn, $results['app']->status);
        $this->assertSame('degraded', $results['app']->message);
    }

    #[Test]
    public function runPreservesExistingFailStatus(): void
    {
        $checker = new HealthChecker(
            checks: [
                new CallbackHealthCheck(
                    name: 'app',
                    check: static fn(): HealthResult => HealthResult::fail(name: 'app', message: 'down'),
                ),
            ],
            warnThresholdMs: 1.0,
        );

        $results = $checker->run();

        $this->assertSame(HealthStatus::Fail, $results['app']->status);
        $this->assertSame('down', $results['app']->message);
    }

    #[Test]
    public function fastPassCheckDoesNotBecomeWarn(): void
    {
        $checker = new HealthChecker(
            checks: [
                new CallbackHealthCheck(
                    name: 'app',
                    check: static fn(): HealthResult => HealthResult::pass(name: 'app'),
                ),
            ],
            warnThresholdMs: 999999.0,
        );

        $results = $checker->run();

        $this->assertSame(HealthStatus::Pass, $results['app']->status);
    }

    #[Test]
    public function runWithClockMeasuresElapsedTime(): void
    {
        $clock = new FakeClock();
        $callCount = 0;

        $checker = new HealthChecker(
            checks: [
                new CallbackHealthCheck(
                    name: 'app',
                    check: static function () use (&$callCount, $clock): HealthResult {
                        $callCount++;

                        if ($callCount === 1) {
                            $clock->advanceByMilliseconds(50.0);
                        }

                        return HealthResult::pass(name: 'app');
                    },
                ),
            ],
            clock: $clock,
            warnThresholdMs: 1000.0,
        );

        $results = $checker->run();

        $this->assertGreaterThan(0.0, $results['app']->elapsedMs);
    }

    #[Test]
    public function runWithClockWarnsOnSlowCheck(): void
    {
        $clock = new FakeClock();

        $checker = new HealthChecker(
            checks: [
                new CallbackHealthCheck(
                    name: 'app',
                    check: static function () use ($clock): HealthResult {
                        $clock->advanceByMilliseconds(500.0);

                        return HealthResult::pass(name: 'app');
                    },
                ),
            ],
            clock: $clock,
            warnThresholdMs: 100.0,
        );

        $results = $checker->run();

        $this->assertSame(HealthStatus::Warn, $results['app']->status);
    }

}
