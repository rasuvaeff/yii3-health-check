<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3HealthCheck\Tests;

use Rasuvaeff\PropertyTesting\ArbitraryInterface;
use Rasuvaeff\PropertyTesting\Gen;
use Rasuvaeff\PropertyTesting\Property;
use Rasuvaeff\Yii3HealthCheck\CallbackHealthCheck;
use Rasuvaeff\Yii3HealthCheck\HealthChecker;
use Rasuvaeff\Yii3HealthCheck\HealthResult;
use Rasuvaeff\Yii3HealthCheck\HealthStatus;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Test]
#[Covers(HealthChecker::class)]
final class HealthCheckerTest
{
    public function runReturnsResultsForAllChecks(): void
    {
        $checker = new HealthChecker(checks: [
            new CallbackHealthCheck(name: 'app', check: static fn(): HealthResult => HealthResult::pass(name: 'app')),
            new CallbackHealthCheck(name: 'db', check: static fn(): HealthResult => HealthResult::pass(name: 'db')),
        ]);

        $results = $checker->run();

        Assert::count($results, 2);
        Assert::array($results)->hasKeys('app', 'db');
    }

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

        Assert::same($results['redis']->status, HealthStatus::Fail);
        Assert::same($results['redis']->message, 'Connection refused');
    }

    public function aggregateStatusReturnsPassWhenAllPass(): void
    {
        $results = [
            'app' => HealthResult::pass(name: 'app'),
            'db' => HealthResult::pass(name: 'db'),
        ];

        Assert::same(HealthChecker::aggregateStatus($results), HealthStatus::Pass);
    }

    public function aggregateStatusReturnsFailWhenAnyFails(): void
    {
        $results = [
            'app' => HealthResult::pass(name: 'app'),
            'db' => HealthResult::fail(name: 'db', message: 'down'),
        ];

        Assert::same(HealthChecker::aggregateStatus($results), HealthStatus::Fail);
    }

    public function aggregateStatusReturnsWarnWhenAnyWarnsAndNoFails(): void
    {
        $results = [
            'app' => HealthResult::pass(name: 'app'),
            'db' => HealthResult::warn(name: 'db', message: 'slow'),
        ];

        Assert::same(HealthChecker::aggregateStatus($results), HealthStatus::Warn);
    }

    public function aggregateStatusReturnsPassForEmptyResults(): void
    {
        Assert::same(HealthChecker::aggregateStatus(results: []), HealthStatus::Pass);
    }

    public function addRegistersCheck(): void
    {
        $checker = new HealthChecker();
        $checker->add(new CallbackHealthCheck(
            name: 'app',
            check: static fn(): HealthResult => HealthResult::pass(name: 'app'),
        ));

        $results = $checker->run();

        Assert::array($results)->hasKeys('app');
    }

    public function runByNameReturnsSingleResult(): void
    {
        $checker = new HealthChecker(checks: [
            new CallbackHealthCheck(name: 'app', check: static fn(): HealthResult => HealthResult::pass(name: 'app')),
            new CallbackHealthCheck(name: 'db', check: static fn(): HealthResult => HealthResult::pass(name: 'db')),
        ]);

        $results = $checker->runByName(name: 'app');

        Assert::count($results, 1);
        Assert::array($results)->hasKeys('app');
    }

    public function runByNameReturnsEmptyForUnknown(): void
    {
        $checker = new HealthChecker();

        $results = $checker->runByName(name: 'unknown');

        Assert::same($results, []);
    }

    public function hasReturnsTrueForRegisteredCheck(): void
    {
        $checker = new HealthChecker(checks: [
            new CallbackHealthCheck(name: 'app', check: static fn(): HealthResult => HealthResult::pass(name: 'app')),
        ]);

        Assert::true($checker->has('app'));
        Assert::false($checker->has('unknown'));
    }

    public function runMeasuresElapsedTime(): void
    {
        $checker = new HealthChecker(checks: [
            new CallbackHealthCheck(name: 'app', check: static fn(): HealthResult => HealthResult::pass(name: 'app')),
        ]);

        $results = $checker->run();

        Assert::true($results['app']->elapsedMs >= 0.0);
    }

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

        Assert::same($results['slow']->status, HealthStatus::Warn);
    }

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

        Assert::same($results['app']->status, HealthStatus::Warn);
        Assert::same($results['app']->message, 'degraded');
    }

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

        Assert::same($results['app']->status, HealthStatus::Fail);
        Assert::same($results['app']->message, 'down');
    }

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

        Assert::same($results['app']->status, HealthStatus::Pass);
    }

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

        Assert::true($results['app']->elapsedMs > 0.0);
    }

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

        Assert::same($results['app']->status, HealthStatus::Warn);
    }

    #[Property(runs: 400)]
    public function aggregateReturnsWorstSeverity(array $codes): void
    {
        $results = self::resultsFromCodes($codes);

        $expected = match (true) {
            \in_array(2, $codes, strict: true) => HealthStatus::Fail,
            \in_array(1, $codes, strict: true) => HealthStatus::Warn,
            default => HealthStatus::Pass,
        };

        Assert::same(HealthChecker::aggregateStatus($results), $expected);
    }

    /** @return array<string, ArbitraryInterface> */
    private function aggregateReturnsWorstSeverityGenerators(): array
    {
        return ['codes' => Gen::nonEmptyArrayOf(Gen::intBetween(0, 2))];
    }

    #[Property(runs: 400)]
    public function aggregateIsOrderIndependent(array $codes): void
    {
        $results = self::resultsFromCodes($codes);

        Assert::same(
            HealthChecker::aggregateStatus(\array_reverse($results)),
            HealthChecker::aggregateStatus($results),
        );
    }

    /** @return array<string, ArbitraryInterface> */
    private function aggregateIsOrderIndependentGenerators(): array
    {
        return ['codes' => Gen::nonEmptyArrayOf(Gen::intBetween(0, 2))];
    }

    /**
     * @param list<int> $codes
     *
     * @return list<HealthResult>
     */
    private static function resultsFromCodes(array $codes): array
    {
        return \array_map(
            static fn(int $code, int $i): HealthResult => match ($code) {
                2 => HealthResult::fail(name: 'check' . $i),
                1 => HealthResult::warn(name: 'check' . $i),
                default => HealthResult::pass(name: 'check' . $i),
            },
            $codes,
            \array_keys($codes),
        );
    }
}
