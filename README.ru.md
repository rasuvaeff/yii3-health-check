# rasuvaeff/yii3-проверка работоспособности
[![Stable Version](https://img.shields.io/packagist/v/rasuvaeff/yii3-health-check.svg)](https://packagist.org/packages/rasuvaeff/yii3-health-check)
[![Total Downloads](https://img.shields.io/packagist/dt/rasuvaeff/yii3-health-check.svg)](https://packagist.org/packages/rasuvaeff/yii3-health-check)
[![Build](https://img.shields.io/github/actions/workflow/status/rasuvaeff/yii3-health-check/build.yml?branch=master)](https://github.com/rasuvaeff/yii3-health-check/actions)
[![Static Analysis](https://img.shields.io/github/actions/workflow/status/rasuvaeff/yii3-health-check/static-analysis.yml?branch=master)](https://github.com/rasuvaeff/yii3-health-check/actions)
[![Psalm Level](https://img.shields.io/badge/Psalm-1-blue.svg)](https://github.com/rasuvaeff/yii3-health-check/actions)
[![PHP](https://img.shields.io/packagist/dependency-v/rasuvaeff/yii3-health-check/php)](https://packagist.org/packages/rasuvaeff/yii3-health-check)
[![License](https://img.shields.io/packagist/l/rasuvaeff/yii3-health-check.svg)](LICENSE.md)
Конечные точки проверки работоспособности для Yii3: `/live`, `/ready`, `/health`. Обработчики запросов PSR-15 с составными проверками, отслеживанием прошедшего времени и поддержкой зондов Kubernetes.

 > Используете помощника по программированию с искусственным интеллектом? [llms.txt](llms.txt) содержит компактную ссылку на API, готовую для вставки в контекст. @@ЛИНИЯ@@
## Требования
- PHP 8.3+
 - `psr/lock` ^1.0
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
### 2. Зарегистрируйте свои чеки в DI
Пакет поставляется с `config/di.php` и `config/params.php` через config-plugin. Расширьте `HealthChecker` своими собственными проверками:

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
### 3. Внедрите свои проверки
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
Для встроенных проверок без специального класса используйте CallbackHealthCheck:

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
## Живость против готовности
| Конечная точка | Обработчик | Чеки | зонд k8s | При неудаче |
 |---|---|---|---|---|
 | `/жить` | `LivenessEndpoint` | Нет (всегда проходит) | `livenessProbe` | Перезапуск контейнера |
 | `/готов` | `ReadinessEndpoint` | Все зарегистрированные чеки | `readinessProbe` | Удален из балансировщика нагрузки |
 | `/здоровье` | `HealthEndpoint` | Все зарегистрированные чеки | Мониторинг/панель мониторинга | — |

 **Правило:** никогда не устанавливайте проверки работоспособности внешних служб (БД, Redis) — медленная БД перезапустит ваш контейнер. Ставьте их только на готовность. @@ЛИНИЯ@@
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
$result->elapsedMs  // float (set by HealthChecker)

$result->withData(['rows' => 42])   // returns new instance
$result->withElapsedMs(12.5)        // returns new instance
$result->toArray()                  // array, omits elapsedMs:0 and data:[], always includes message
```
### `Статус здоровья`
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
Правила агрегации:

 | Состояние | Результат |
 |---|---|
 | Любая `провалка` | `провалиться` |
 | Никаких «предупреждений», никаких «сбоев» | `предупреждать` |
 | Все `пройдено` | `пройти` | @@ЛИНИЯ@@
### Интерфейс «HealthCheck»
```php
interface HealthCheck
{
    public function name(): string;   // /^[a-z][a-z0-9_.-]*$/
    public function check(): HealthResult;
}
```
Исключение, выданное функцией `check()`, перехватывается и преобразуется в `HealthResult::fail`. @@ЛИНИЯ@@
### `LivenessEndpoint`
```php
new LivenessEndpoint(
    responseFactory: $factory,
    statusMessage: 'alive',    // optional, default 'alive'
)
```
Всегда возвращает HTTP 200. Никогда не зависит от внешних сервисов. @@ЛИНИЯ@@
```json
{"status":"pass","message":"alive"}
```
### `ReadinessEndpoint`/`HealthEndpoint`
```php
new ReadinessEndpoint(checker: $checker, responseFactory: $factory)
new HealthEndpoint(checker: $checker, responseFactory: $factory)
```
Оба запускают все зарегистрированные проверки и возвращают один и тот же формат JSON. @@ЛИНИЯ@@
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
`warnThresholdMs` (по умолчанию: 1000 мс) автоматически обновляет `pass` → `warn`, когда проверка занимает слишком много времени. Существующие статусы «предупреждение» и «сбой» никогда не понижаются:

```php
// warnThresholdMs: 500
// database check took 750ms → upgraded to warn automatically
{"name":"database","status":"warn","message":"Check took 750.0ms (threshold: 500.0ms)","elapsedMs":750.0}
```
Настройте через `params.php`:

```php
'rasuvaeff/yii3-health-check' => [
    'warnThresholdMs' => 500.0,
    'livenessMessage' => 'alive',
],
```
## Зонды Kubernetes
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
- Проверенные имена проверены: `/^[a-z][a-z0-9_.-]*$/`
 - Обратные вызовы контролируются разработчиком, пользовательский ввод не выполняется
 - Liveness никогда не раскрывает внутреннее состояние службы

## Примеры
См. [`examples/`](examples/) для работоспособных скриптов и полное руководство по подключению Yii3. @@ЛИНИЯ@@
## Разработка
```bash
make install
make build
make cs-fix
make mutation
```
## Лицензия
BSD-3-пункт. См. [LICENSE.md](LICENSE.md).
