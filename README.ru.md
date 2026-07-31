# rasuvaeff/yii3-health-check

[![Stable Version](https://img.shields.io/packagist/v/rasuvaeff/yii3-health-check.svg)](https://packagist.org/packages/rasuvaeff/yii3-health-check)
[![Total Downloads](https://img.shields.io/packagist/dt/rasuvaeff/yii3-health-check.svg)](https://packagist.org/packages/rasuvaeff/yii3-health-check)
[![Build](https://img.shields.io/github/actions/workflow/status/rasuvaeff/yii3-health-check/build.yml?branch=master)](https://github.com/rasuvaeff/yii3-health-check/actions)
[![Static Analysis](https://img.shields.io/github/actions/workflow/status/rasuvaeff/yii3-health-check/static-analysis.yml?branch=master)](https://github.com/rasuvaeff/yii3-health-check/actions)
[![Psalm Level](https://img.shields.io/badge/Psalm-1-blue.svg)](https://github.com/rasuvaeff/yii3-health-check/actions)
[![PHP](https://img.shields.io/packagist/dependency-v/rasuvaeff/yii3-health-check/php)](https://packagist.org/packages/rasuvaeff/yii3-health-check)
[![License](https://img.shields.io/packagist/l/rasuvaeff/yii3-health-check.svg)](LICENSE.md)
[English version](README.md)

Health check эндпоинты для Yii3: `/live`, `/ready`, `/health`. PSR-15-обработчики
запросов с композитными проверками, замером затраченного времени и поддержкой
Kubernetes-проб.

> Используете AI-ассистента? В [llms.txt](llms.txt) — компактный API-справочник,
> который можно вставить прямо в контекст.

## Требования

- PHP 8.3+
- `psr/clock` ^1.0
- `psr/http-factory` ^1.0
- `psr/http-message` ^2.0
- `psr/http-server-handler` ^1.0

## Установка

```bash
composer require rasuvaeff/yii3-health-check
```

## Использование

### 1. Регистрация маршрутов

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

### 2. Регистрация проверок в DI

Пакет поставляет `config/di.php` и `config/params.php` через config-plugin. Расширьте `HealthChecker` собственными проверками:

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

### 3. Реализация собственных проверок

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

Для разовых проверок без выделенного класса используйте `CallbackHealthCheck`:

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

| Эндпоинт | Обработчик | Проверки | k8s probe | При сбое |
|---|---|---|---|---|
| `/live` | `LivenessEndpoint` | Нет (всегда pass) | `livenessProbe` | Перезапуск контейнера |
| `/ready` | `ReadinessEndpoint` | Все зарегистрированные проверки | `readinessProbe` | Вывод из балансировщика |
| `/health` | `HealthEndpoint` | Все зарегистрированные проверки | Мониторинг/дашборд | — |

**Правило:** никогда не помещайте проверки внешних сервисов (БД, Redis) в liveness —
медленная БД приведёт к перезапуску контейнера. Их место — только в readiness.

## Справочник по API

### `HealthResult`

```php
HealthResult::pass(name: 'db')
HealthResult::pass(name: 'db', message: 'Connected')
HealthResult::warn(name: 'db', message: 'Slow', data: ['latencyMs' => 950])
HealthResult::fail(name: 'db', message: 'Connection refused')

$result->name       // string
$result->status     // HealthStatus
$result->message    // string
$result->data       // array<string, mixed>
$result->elapsedMs  // float (устанавливается HealthChecker)

$result->withData(['rows' => 42])   // возвращает новый экземпляр
$result->withElapsedMs(12.5)        // возвращает новый экземпляр
$result->toArray()                  // array, пропускает elapsedMs:0 и data:[], всегда включает message
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
    clock: $psrClock,          // опционально PSR-20, по умолчанию microtime()
    warnThresholdMs: 500.0,    // по умолчанию 1000.0
);

$checker->add($check);                        // добавить проверку во время выполнения
$checker->has('database');                    // bool
$results = $checker->run();                   // array<string, HealthResult>
$results = $checker->runByName('database');   // array<string, HealthResult>

HealthChecker::aggregateStatus($results);     // HealthStatus
```

Правила агрегации:

| Условие | Результат |
|---|---|
| Есть `fail` | `fail` |
| Есть `warn`, без `fail` | `warn` |
| Все `pass` | `pass` |

Особенности поведения:

- `runByName()` с неизвестным именем возвращает `[]`, а `aggregateStatus([])` — `pass`: опечатка в имени проверки выглядит как здоровая система. Если имя приходит из конфигурации — сначала проверяйте `has()`.
- Проверки хранятся по имени: `add()` и конструктор молча заменяют существующую проверку с тем же именем.

### Интерфейс `HealthCheck`

```php
interface HealthCheck
{
    public function name(): string;   // /^[a-z][a-z0-9_.-]*\z/
    public function check(): HealthResult;
}
```

Исключение, брошенное из `check()`, перехватывается и превращается в `HealthResult::fail`.

### `LivenessEndpoint`

```php
new LivenessEndpoint(
    responseFactory: $factory,
    statusMessage: 'alive',    // опционально, по умолчанию 'alive'
)
```

Всегда возвращает HTTP 200. Никогда не зависит от внешних сервисов.

```json
{"status":"pass","message":"alive"}
```

### `ReadinessEndpoint` / `HealthEndpoint`

```php
new ReadinessEndpoint(checker: $checker, responseFactory: $factory)
new HealthEndpoint(checker: $checker, responseFactory: $factory)
```

Оба запускают все зарегистрированные проверки и возвращают одинаковый JSON-формат.

```json
{
    "status": "warn",
    "checks": {
        "database": {"name":"database","status":"pass","message":"","elapsedMs":2.1},
        "redis":    {"name":"redis","status":"warn","message":"Check took 1243.0ms (threshold: 1000.0ms)","elapsedMs":1243.0}
    }
}
```

## Порог предупреждения

`warnThresholdMs` (по умолчанию: 1000ms) автоматически повышает `pass` → `warn`,
когда проверка выполняется слишком долго. Существующие статусы `warn` и `fail`
никогда не понижаются:

```php
// warnThresholdMs: 500
// database check took 750ms → upgraded to warn automatically
{"name":"database","status":"warn","message":"Check took 750.0ms (threshold: 500.0ms)","elapsedMs":750.0}
```

Настройка через `params.php`:

```php
'rasuvaeff/yii3-health-check' => [
    'warnThresholdMs' => 500.0,
    'livenessMessage' => 'alive',
],
```

## Kubernetes-пробы

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

## Безопасность

- Имена проверок валидируются: `/^[a-z][a-z0-9_.-]*\z/` (якорь `\z` — trailing newline отклоняется)
- Колбэки контролирует разработчик — пользовательский ввод не выполняется
- Liveness никогда не раскрывает состояние внутренних сервисов
- `/ready` и `/health` раскрывают топологию инфраструктуры и сырые сообщения исключений (`$e->getMessage()` может содержать хосты или DSN) — отдавайте их на внутреннем порту или ограничьте доступ (network policy, ACL, auth middleware)
- Per-check timeout отсутствует (синхронный PHP-FPM не может его обеспечить) — задавайте connect/read timeout внутри каждой проверки, чтобы зависшая зависимость не повесила `/ready`

## Примеры

См. [`examples/`](examples/) — запускаемые скрипты и полное руководство по
интеграции с Yii3.

## Разработка

```bash
make install
make build
make cs-fix
make mutation
```

## Лицензия

BSD-3-Clause. См. [LICENSE.md](LICENSE.md).
