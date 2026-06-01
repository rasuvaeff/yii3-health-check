<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3HealthCheck\Tests;

use Psr\Clock\ClockInterface;

/**
 * @internal
 */
final class FakeClock implements ClockInterface
{
    private \DateTimeImmutable $now;

    public function __construct()
    {
        $this->now = new \DateTimeImmutable();
    }

    public function advanceByMilliseconds(float $ms): void
    {
        $this->now = $this->now->modify(sprintf('+%f second', $ms / 1000.0));
    }

    #[\Override]
    public function now(): \DateTimeImmutable
    {
        return $this->now;
    }
}
