<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3HealthCheck\Tests;

use Rasuvaeff\PropertyTesting\ArbitraryInterface;
use Rasuvaeff\PropertyTesting\Classify;
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

    public function thresholdUpgradePreservesMessageAndData(): void
    {
        $clock = new FakeClock();

        $checker = new HealthChecker(
            checks: [
                new CallbackHealthCheck(
                    name: 'db',
                    check: static function () use ($clock): HealthResult {
                        $clock->advanceByMilliseconds(500.0);

                        return HealthResult::pass(name: 'db', message: 'Connected', data: ['rows' => 42]);
                    },
                ),
            ],
            clock: $clock,
            warnThresholdMs: 100.0,
        );

        $result = $checker->run()['db'];

        Assert::same($result->status, HealthStatus::Warn);
        Assert::same($result->data, ['rows' => 42]);
        Assert::same($result->message, 'Connected; Check took 500.0ms (threshold: 100.0ms)');
    }

    public function thresholdUpgradeWithEmptyMessageUsesThresholdMessageOnly(): void
    {
        $clock = new FakeClock();

        $checker = new HealthChecker(
            checks: [
                new CallbackHealthCheck(
                    name: 'db',
                    check: static function () use ($clock): HealthResult {
                        $clock->advanceByMilliseconds(500.0);

                        return HealthResult::pass(name: 'db');
                    },
                ),
            ],
            clock: $clock,
            warnThresholdMs: 100.0,
        );

        Assert::same($checker->run()['db']->message, 'Check took 500.0ms (threshold: 100.0ms)');
    }

    public function elapsedExactlyAtThresholdDoesNotBecomeWarn(): void
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
            warnThresholdMs: 500.0,
        );

        $results = $checker->run();

        Assert::same($results['app']->status, HealthStatus::Pass);
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
        $results = $this->resultsFromCodes($codes);

        $expected = match (true) {
            \in_array(2, $codes, strict: true) => HealthStatus::Fail,
            \in_array(1, $codes, strict: true) => HealthStatus::Warn,
            default => HealthStatus::Pass,
        };

        // Worst-of has three outcomes and each has to be reached: a run that
        // only ever saw a Fail somewhere would say nothing about Warn winning
        // over Pass.
        Classify::cover($expected === HealthStatus::Pass, 'all pass', 5.0);
        Classify::cover($expected === HealthStatus::Warn, 'warn wins', 10.0);
        Classify::cover($expected === HealthStatus::Fail, 'fail wins', 10.0);

        Assert::same(HealthChecker::aggregateStatus($results), $expected);
    }

    /** @return array<string, ArbitraryInterface> */
    public static function aggregateReturnsWorstSeverityGenerators(): array
    {
        // Bounded length: with the default maximum an all-pass list is
        // vanishingly rare, and the coverage gate above would never be met.
        return ['codes' => Gen::nonEmptyArrayOf(Gen::intBetween(0, 2), 6)];
    }

    /** @return iterable<string, array{list<int>}> */
    public static function aggregateReturnsWorstSeverityExamples(): iterable
    {
        yield 'single pass' => [[0]];
        yield 'single warn' => [[1]];
        yield 'single fail' => [[2]];
        yield 'fail behind a pass' => [[0, 2]];
        yield 'warn never demoted by later passes' => [[1, 0, 0]];
        yield 'fail wins over an earlier warn' => [[1, 2]];
    }

    #[Property(runs: 400)]
    public function aggregateIsOrderIndependent(array $codes): void
    {
        $results = $this->resultsFromCodes($codes);

        Assert::same(
            HealthChecker::aggregateStatus(\array_reverse($results)),
            HealthChecker::aggregateStatus($results),
        );
    }

    /** @return array<string, ArbitraryInterface> */
    public static function aggregateIsOrderIndependentGenerators(): array
    {
        return ['codes' => Gen::nonEmptyArrayOf(Gen::intBetween(0, 2))];
    }

    #[Property(runs: 300)]
    public function aggregateSurvivesAnyPermutation(array $pairs): void
    {
        // Reversing is one permutation out of n!; a fold that is merely
        // symmetric would pass that and still depend on the order. The second
        // element of each pair is a generated sort key, so the permutation is
        // drawn rather than fixed — and stays reproducible from the seed.
        $codes = \array_map(static fn(array $pair): int => $pair[0], $pairs);

        $permuted = $pairs;
        \usort($permuted, static fn(array $a, array $b): int => $a[1] <=> $b[1]);
        $permutedCodes = \array_map(static fn(array $pair): int => $pair[0], $permuted);

        Classify::when($permutedCodes === $codes, 'identity permutation');

        Assert::same(
            HealthChecker::aggregateStatus($this->resultsFromCodes($permutedCodes)),
            HealthChecker::aggregateStatus($this->resultsFromCodes($codes)),
        );
    }

    /** @return array<string, ArbitraryInterface> */
    public static function aggregateSurvivesAnyPermutationGenerators(): array
    {
        return [
            'pairs' => Gen::nonEmptyArrayOf(
                Gen::tuple(Gen::intBetween(0, 2), Gen::intBetween(0, 1_000)),
                8,
            ),
        ];
    }

    #[Property(runs: 300)]
    public function elapsedTimeDecidesTheUpgradeAndNeverTouchesData(
        array $data,
        int $thresholdMs,
        bool $slow,
        int $offsetMs,
    ): void {
        // Built rather than filtered, and never landing on the threshold
        // itself: elapsed is derived from a clock reading in milliseconds-
        // since-epoch, so a comparison at exactly the boundary is decided by
        // double rounding rather than by the rule under test.
        $elapsedMs = $slow ? $thresholdMs + $offsetMs : max(0, $thresholdMs - $offsetMs);
        $clock = new FakeClock();

        $checker = new HealthChecker(
            checks: [
                new CallbackHealthCheck(
                    name: 'db',
                    check: static function () use ($clock, $data, $elapsedMs): HealthResult {
                        $clock->advanceByMilliseconds((float) $elapsedMs);

                        return HealthResult::pass(name: 'db', data: $data);
                    },
                ),
            ],
            clock: $clock,
            warnThresholdMs: (float) $thresholdMs,
        );

        Classify::cover($slow, 'upgraded to warn', 30.0);
        Classify::cover(!$slow, 'left as pass', 30.0);
        Classify::when($data === [], 'empty payload');

        $result = $checker->run()['db'];

        Assert::same($result->status, $slow ? HealthStatus::Warn : HealthStatus::Pass);
        // The upgrade rewrites the status and the message; whatever the check
        // reported has to travel through untouched, which is what makes a warn
        // actionable.
        Assert::same($result->data, $data);
    }

    /** @return array<string, ArbitraryInterface> */
    public static function elapsedTimeDecidesTheUpgradeAndNeverTouchesDataGenerators(): array
    {
        return [
            // A dictionary, not a list: check payloads are string-keyed maps
            // in every documented usage.
            'data' => Gen::dictOf(
                Gen::stringFrom('abcdefghijklmnopqrstuvwxyz_', minLength: 1, maxLength: 8),
                Gen::intBetween(-1000, 1000),
                maxSize: 6,
            ),
            'thresholdMs' => Gen::intBetween(50, 300),
            'slow' => Gen::bool(),
            'offsetMs' => Gen::intBetween(5, 150),
        ];
    }

    /** @return iterable<string, array{array<string, int>, int, bool, int}> */
    public static function elapsedTimeDecidesTheUpgradeAndNeverTouchesDataExamples(): iterable
    {
        yield 'instant check with a payload' => [['queue' => 3], 100, false, 100];
        yield 'well over the threshold' => [[], 100, true, 150];
        yield 'empty payload survives the upgrade' => [[], 50, true, 5];
    }

    #[Property(runs: 200)]
    public function runIsKeyedByEveryDistinctCheckName(array $names): void
    {
        $checker = new HealthChecker(
            checks: \array_map(
                static fn(string $name): CallbackHealthCheck => new CallbackHealthCheck(
                    name: $name,
                    check: static fn(): HealthResult => HealthResult::pass(name: $name),
                ),
                $names,
            ),
        );

        $results = $checker->run();

        Assert::same(\array_keys($results), $names);
        Assert::same(HealthChecker::aggregateStatus($results), HealthStatus::Pass);
    }

    /** @return array<string, ArbitraryInterface> */
    public static function runIsKeyedByEveryDistinctCheckNameGenerators(): array
    {
        // Unique by construction: the checker keys by name, so duplicates
        // would collapse and turn a count assertion into a discard. The names
        // themselves come from the format HealthResult validates, so the
        // generator and the validator cannot drift apart into a discard
        // either.
        return [
            'names' => Gen::uniqueArrayOf(
                Gen::regex('[a-z][a-z0-9_.-]{0,5}'),
                minSize: 1,
                maxSize: 5,
            ),
        ];
    }

    /**
     * @param list<int> $codes
     *
     * @return list<HealthResult>
     */
    private function resultsFromCodes(array $codes): array
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
