<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3HealthCheck;

/**
 * @api
 */
interface HealthCheck
{
    public function name(): string;

    public function check(): HealthResult;
}
