# rasuvaeff/yii3-health-check

[![Stable Version](https://img.shields.io/packagist/v/rasuvaeff/yii3-health-check.svg)](https://packagist.org/packages/rasuvaeff/yii3-health-check)
[![Total Downloads](https://img.shields.io/packagist/dt/rasuvaeff/yii3-health-check.svg)](https://packagist.org/packages/rasuvaeff/yii3-health-check)
[![Build](https://img.shields.io/github/actions/workflow/status/rasuvaeff/yii3-health-check/build.yml?branch=master)](https://github.com/rasuvaeff/yii3-health-check/actions)
[![Static Analysis](https://img.shields.io/github/actions/workflow/status/rasuvaeff/yii3-health-check/static-analysis.yml?branch=master)](https://github.com/rasuvaeff/yii3-health-check/actions)
[![Psalm Level](https://img.shields.io/badge/Psalm-1-blue.svg)](https://github.com/rasuvaeff/yii3-health-check/actions)
[![PHP](https://img.shields.io/packagist/dependency-v/rasuvaeff/yii3-health-check/php)](https://packagist.org/packages/rasuvaeff/yii3-health-check)
[![License](https://img.shields.io/packagist/l/rasuvaeff/yii3-health-check.svg)](LICENSE.md)
[Русская версия](README.ru.md)

Health check endpoints for Yii3: `/live`, `/ready`, `/health`. PSR-15 request handlers with composite checks, elapsed time tracking and Kubernetes probe support.

> Using an AI coding assistant? [llms.txt](llms.txt) has a compact API reference ready to paste into context.

## Requirements

- PHP 8.3+
- `psr/clock` ^1.0
- `psr/http-factory` ^1.0
- `psr/http-message` ^2.0
- `psr/http-server-handler` ^1.0

## Installation

```bash
composer require rasuvaeff/yii3-health-check
```

## Usage

### 1. Register routes

```php
// config/routes.php
use Rasuvaeff\Yii3HealthCheck\HealthEndpoint;
use Rasuvaeff\Yii3HealthCheck\LivenessEndpoint;
use Rasuvaeff\Yii3HealthCheck\ReadinessEndpoint;
use Yiisoft\Router\Route;

return [
    Route::get('/live')->action(LivenessEndpoint::class)->name('health.live'),
    Route::get('/ready')->action(ReadinessEndpoint::class)->name('health.ready'),
    Route::get('/health')->action(HealthEndpoint::class)->name('health.full'),
];
```

### 2. Register your checks in DI

The package ships `config/di.php` and `config/params.php` via config-plugin. Extend `HealthChecker` with your own checks:

```php
// config/di.php
use App\Infrastructure\Health\DatabaseHealthCheck;
use App\Infrastructure\Health\RedisHealthCheck;
use Rasuvaeff\Yii3HealthCheck\HealthChecker;
use Yiisoft\Definitions\Reference;

return [
    HealthChecker::class => [
        '__construct()' => [
            'checks' => [
                Reference::to(DatabaseHealthCheck::class),
                Reference::to(RedisHealthCheck::class),
            ],
            'warnThresholdMs' => $params['rasuvaeff/yii3-health-check']['warnThresholdMs'],
        ],
    ],
];
```

### 3. Implement your checks

```php
// src/Infrastructure/Health/DatabaseHealthCheck.php
use Rasuvaeff\Yii3HealthCheck\HealthCheck;
use Rasuvaeff\Yii3HealthCheck\HealthResult;
use Yiisoft\Db\Connection\ConnectionInterface;

final readonly class DatabaseHealthCheck implements HealthCheck
{
    public function __construct(private ConnectionInterface $db) {}

    public function name(): string
    {
        return 'database';
    }

    public function check(): HealthResult
    {
        try {
            $this->db->createCommand('SELECT 1')->queryScalar();
            return HealthResult::pass(name: 'database');
        } catch (\Throwable $e) {
            return HealthResult::fail(name: 'database', message: $e->getMessage());
        }
    }
}
```

For inline checks without a dedicated class use `CallbackHealthCheck`:

```php
use Rasuvaeff\Yii3HealthCheck\CallbackHealthCheck;
use Rasuvaeff\Yii3HealthCheck\HealthResult;

new CallbackHealthCheck(
    name: 'disk',
    check: static function (): HealthResult {
        $free = disk_free_space('/');
        if ($free === false) {
            return HealthResult::fail(name: 'disk', message: 'Cannot read disk stats');
        }
        if ($free < 100 * 1024 * 1024) {
            return HealthResult::warn(name: 'disk', message: 'Less than 100MB free',
                data: ['freeBytes' => $free]);
        }
        return HealthResult::pass(name: 'disk');
    },
)
```

## Liveness vs Readiness

| Endpoint | Handler | Checks | k8s probe | On failure |
|---|---|---|---|---|
| `/live` | `LivenessEndpoint` | None (always pass) | `livenessProbe` | Container restart |
| `/ready` | `ReadinessEndpoint` | All registered checks | `readinessProbe` | Removed from load balancer |
| `/health` | `HealthEndpoint` | All registered checks | Monitoring/dashboard | — |

**Rule:** never put external service checks (DB, Redis) in liveness — a slow DB would restart your container. Put them only in readiness.

`/ready` and `/health` run the same checks and return the same JSON — the difference is the audience. `/ready` is for automation (load balancer, Kubernetes) that decides whether to route traffic; `/health` is for humans, dashboards and external monitoring. Keeping them as separate routes lets you apply different access rules — e.g. `/ready` reachable by the orchestrator only, `/health` behind auth.

## API reference

### `HealthResult`

```php
HealthResult::pass(name: 'db')
HealthResult::pass(name: 'db', message: 'Connected', data: ['latencyMs' => 2.1])
HealthResult::warn(name: 'db', message: 'Slow', data: ['latencyMs' => 950])
HealthResult::fail(name: 'db', message: 'Connection refused', data: ['errno' => 111])

$result->name       // string
$result->status     // HealthStatus
$result->message    // string
$result->data       // array<string, mixed>
$result->elapsedMs  // float (set by HealthChecker)

$result->withData(['rows' => 42])   // returns new instance
$result->withElapsedMs(12.5)        // returns new instance
$result->toArray()                  // array, omits elapsedMs:0 and data:[], always includes message
```

### `HealthStatus`

```php
HealthStatus::Pass  // 'pass' → HTTP 200
HealthStatus::Warn  // 'warn' → HTTP 200
HealthStatus::Fail  // 'fail' → HTTP 503
```

### `HealthChecker`

```php
$checker = new HealthChecker(
    checks: [$dbCheck, $redisCheck],
    clock: $psrClock,          // optional PSR-20, default microtime()
    warnThresholdMs: 500.0,    // default 1000.0
);

$checker->add($check);                        // add a check at runtime
$checker->has('database');                    // bool
$results = $checker->run();                   // array<string, HealthResult>
$results = $checker->runByName('database');   // array<string, HealthResult>

HealthChecker::aggregateStatus($results);     // HealthStatus
```

Aggregation rules:

| Condition | Result |
|---|---|
| Any `fail` | `fail` |
| Any `warn`, no `fail` | `warn` |
| All `pass` | `pass` |

Behavior notes:

- `runByName()` with an unknown name returns `[]`, and `aggregateStatus([])` is `pass` — a typo in the check name looks healthy. Guard with `has()` when the name comes from configuration.
- Checks are keyed by name: `add()` and the constructor silently replace an existing check with the same name.

### `HealthCheck` interface

```php
interface HealthCheck
{
    public function name(): string;   // /^[a-z][a-z0-9_.-]*\z/
    public function check(): HealthResult;
}
```

Exception thrown from `check()` is caught and converted to `HealthResult::fail`.

### `LivenessEndpoint`

```php
new LivenessEndpoint(
    responseFactory: $factory,
    statusMessage: 'alive',    // optional, default 'alive'
)
```

Always returns HTTP 200. Never depends on external services.

```json
{"status":"pass","message":"alive"}
```

### `ReadinessEndpoint` / `HealthEndpoint`

```php
new ReadinessEndpoint(checker: $checker, responseFactory: $factory)
new HealthEndpoint(checker: $checker, responseFactory: $factory)
```

Both run all registered checks and return the same JSON format.

```json
{
    "status": "warn",
    "checks": {
        "database": {"name":"database","status":"pass","message":"","elapsedMs":2.1},
        "redis":    {"name":"redis","status":"warn","message":"Check took 1243.0ms (threshold: 1000.0ms)","elapsedMs":1243.0}
    }
}
```

## Warn threshold

`warnThresholdMs` (default: 1000ms) automatically upgrades `pass` → `warn` when a check takes too long. Existing `warn` and `fail` statuses are never downgraded. The original `message` is kept (the threshold note is appended after `; `) and `data` is preserved:

```php
// warnThresholdMs: 500
// database check took 750ms → upgraded to warn automatically
{"name":"database","status":"warn","message":"Connected; Check took 750.0ms (threshold: 500.0ms)","elapsedMs":750.0}
```

Configure via `params.php`:

```php
'rasuvaeff/yii3-health-check' => [
    'warnThresholdMs' => 500.0,
    'livenessMessage' => 'alive',
],
```

## Kubernetes probes

```yaml
livenessProbe:
  httpGet:
    path: /live
    port: 8080
  initialDelaySeconds: 5
  periodSeconds: 10
  failureThreshold: 3

readinessProbe:
  httpGet:
    path: /ready
    port: 8080
  initialDelaySeconds: 5
  periodSeconds: 10
  failureThreshold: 3
```

## Security

- Check names validated: `/^[a-z][a-z0-9_.-]*\z/` (`\z` anchor — a trailing newline is rejected)
- Callbacks are developer-controlled, no user input executed
- Liveness never exposes internal service state
- `/ready` and `/health` expose infrastructure topology and raw exception messages (`$e->getMessage()` may contain hosts or DSN details) — serve them on an internal port or restrict access (network policy, ACL, auth middleware)
- There is no per-check timeout (synchronous PHP-FPM cannot enforce one) — set connect/read timeouts inside each check so a hung dependency cannot stall `/ready`

## Examples

See [`examples/`](examples/) for runnable scripts and a full Yii3 wiring guide.

## Development

```bash
make install
make build
make cs-fix
make mutation
```

## License

BSD-3-Clause. See [LICENSE.md](LICENSE.md).
