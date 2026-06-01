<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Rasuvaeff\Yii3HealthCheck\CallbackHealthCheck;
use Rasuvaeff\Yii3HealthCheck\HealthChecker;
use Rasuvaeff\Yii3HealthCheck\HealthResult;

$checker = new HealthChecker(checks: [
    new CallbackHealthCheck(
        name: 'app',
        check: static fn(): HealthResult => HealthResult::pass(name: 'app'),
    ),
    new CallbackHealthCheck(
        name: 'database',
        check: static fn(): HealthResult => HealthResult::pass(name: 'database', message: 'PostgreSQL connected'),
    ),
]);

$results = $checker->run();
$status = HealthChecker::aggregateStatus($results);

echo "Overall status: {$status->value}\n\n";

foreach ($results as $name => $result) {
    echo "  {$name}: {$result->status->value}";
    echo $result->message !== '' ? " ({$result->message})" : '';
    echo sprintf(' [%.2fms]', $result->elapsedMs);
    echo "\n";
}
