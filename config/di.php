<?php

declare(strict_types=1);

use Rasuvaeff\Yii3HealthCheck\HealthChecker;
use Rasuvaeff\Yii3HealthCheck\HealthEndpoint;
use Rasuvaeff\Yii3HealthCheck\LivenessEndpoint;
use Rasuvaeff\Yii3HealthCheck\ReadinessEndpoint;

/** @var array $params */

return [
    HealthChecker::class => [
        '__construct()' => [
            'warnThresholdMs' => $params['rasuvaeff/yii3-health-check']['warnThresholdMs'],
        ],
    ],
    HealthEndpoint::class => HealthEndpoint::class,
    ReadinessEndpoint::class => ReadinessEndpoint::class,
    LivenessEndpoint::class => [
        '__construct()' => [
            'statusMessage' => $params['rasuvaeff/yii3-health-check']['livenessMessage'],
        ],
    ],
];
