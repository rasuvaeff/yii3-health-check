<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3HealthCheck\Benchmarks;

use Rasuvaeff\Yii3HealthCheck\HealthChecker;
use Rasuvaeff\Yii3HealthCheck\HealthResult;
use Testo\Bench;

final class HealthCheckerBench
{
    #[Bench(
        callables: [
            'five-results' => [self::class, 'aggregateFive'],
        ],
        calls: 1_000,
        iterations: 10,
    )]
    public static function aggregateOne(): \Rasuvaeff\Yii3HealthCheck\HealthStatus
    {
        return HealthChecker::aggregateStatus([
            HealthResult::pass(name: 'db'),
        ]);
    }

    public static function aggregateFive(): \Rasuvaeff\Yii3HealthCheck\HealthStatus
    {
        return HealthChecker::aggregateStatus([
            HealthResult::pass(name: 'db'),
            HealthResult::pass(name: 'cache'),
            HealthResult::pass(name: 'queue'),
            HealthResult::pass(name: 'storage'),
            HealthResult::pass(name: 'smtp'),
        ]);
    }
}
