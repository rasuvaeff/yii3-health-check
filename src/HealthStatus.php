<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3HealthCheck;

/**
 * @api
 */
enum HealthStatus: string
{
    case Pass = 'pass';
    case Warn = 'warn';
    case Fail = 'fail';
}
