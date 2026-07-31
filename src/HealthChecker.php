<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3HealthCheck;

use Psr\Clock\ClockInterface;

/**
 * @api
 */
final class HealthChecker
{
    /** @var array<string, HealthCheck> */
    private array $checks = [];

    /**
     * @param iterable<HealthCheck> $checks
     */
    public function __construct(
        iterable $checks = [],
        private readonly ?ClockInterface $clock = null,
        private readonly float $warnThresholdMs = 1000.0,
    ) {
        foreach ($checks as $check) {
            $this->checks[$check->name()] = $check;
        }
    }

    public function add(HealthCheck $check): void
    {
        $this->checks[$check->name()] = $check;
    }

    /**
     * @return array<string, HealthResult>
     */
    public function run(): array
    {
        $results = [];

        foreach ($this->checks as $check) {
            $results[$check->name()] = $this->runCheck($check);
        }

        return $results;
    }

    /**
     * @return array<string, HealthResult>
     */
    public function runByName(string $name): array
    {
        if (!isset($this->checks[$name])) {
            return [];
        }

        return [$name => $this->runCheck($this->checks[$name])];
    }

    public function has(string $name): bool
    {
        return isset($this->checks[$name]);
    }

    /**
     * @param array<string, HealthResult> $results
     */
    public static function aggregateStatus(array $results): HealthStatus
    {
        $hasWarn = false;

        foreach ($results as $result) {
            if ($result->status === HealthStatus::Fail) {
                return HealthStatus::Fail;
            }

            if ($result->status === HealthStatus::Warn) {
                $hasWarn = true;
            }
        }

        return $hasWarn ? HealthStatus::Warn : HealthStatus::Pass;
    }

    private function runCheck(HealthCheck $check): HealthResult
    {
        $start = $this->nowMicrotime();

        try {
            $result = $check->check();
        } catch (\Throwable $e) {
            $elapsed = $this->elapsedMs($start);

            return HealthResult::fail(
                name: $check->name(),
                message: $e->getMessage(),
            )->withElapsedMs($elapsed);
        }

        $elapsed = $this->elapsedMs($start);
        $result = $result->withElapsedMs($elapsed);

        if ($result->status === HealthStatus::Pass && $elapsed > $this->warnThresholdMs) {
            $thresholdMessage = sprintf('Check took %.1fms (threshold: %.1fms)', $elapsed, $this->warnThresholdMs);

            $result = HealthResult::warn(
                name: $check->name(),
                message: $result->message === '' ? $thresholdMessage : $result->message . '; ' . $thresholdMessage,
                data: $result->data,
            )->withElapsedMs($elapsed);
        }

        return $result;
    }

    private function nowMicrotime(): float
    {
        if ($this->clock instanceof \Psr\Clock\ClockInterface) {
            $now = $this->clock->now();

            return (float) $now->format('U.u') * 1000.0;
        }

        return microtime(true) * 1000.0;
    }

    private function elapsedMs(float $start): float
    {
        return $this->nowMicrotime() - $start;
    }
}
