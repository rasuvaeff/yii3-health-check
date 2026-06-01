<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3HealthCheck;

enum HealthStatus: string
{
    case Pass = 'pass';
    case Warn = 'warn';
    case Fail = 'fail';
}
