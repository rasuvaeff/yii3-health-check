<?php

declare(strict_types=1);

/**
 * How to wire yii3-health-check into a Yii3 application.
 *
 * Sections:
 *   1. Health check implementations (DB, Redis, Memcache, HTTP site check)
 *   2. DI config
 *   3. Routes config
 *   4. Kubernetes probe config
 *   5. Example JSON responses
 *
 * This file does not run standalone — copy the relevant parts into your app.
 */

// =========================================================================
// 1. Health check implementations
//    Place in src/Infrastructure/Health/
// =========================================================================

// --- DatabaseHealthCheck -------------------------------------------------
//
// final readonly class DatabaseHealthCheck implements HealthCheck
// {
//     public function __construct(private \Yiisoft\Db\Connection\ConnectionInterface $db) {}
//
//     public function name(): string { return 'database'; }
//
//     public function check(): HealthResult
//     {
//         try {
//             $this->db->createCommand('SELECT 1')->queryScalar();
//             return HealthResult::pass(name: 'database');
//         } catch (\Throwable $e) {
//             return HealthResult::fail(name: 'database', message: $e->getMessage());
//         }
//     }
// }

// --- RedisHealthCheck ----------------------------------------------------
//
// Uses ext-redis or any PSR Redis adapter.
//
// final readonly class RedisHealthCheck implements HealthCheck
// {
//     public function __construct(private \Redis $redis) {}
//
//     public function name(): string { return 'redis'; }
//
//     public function check(): HealthResult
//     {
//         try {
//             $this->redis->ping();
//             return HealthResult::pass(name: 'redis');
//         } catch (\Throwable $e) {
//             return HealthResult::fail(name: 'redis', message: $e->getMessage());
//         }
//     }
// }

// --- MemcacheHealthCheck -------------------------------------------------
//
// Uses ext-memcached.
//
// final readonly class MemcacheHealthCheck implements HealthCheck
// {
//     public function __construct(private \Memcached $memcached) {}
//
//     public function name(): string { return 'memcache'; }
//
//     public function check(): HealthResult
//     {
//         try {
//             $key = '__health__';
//             $this->memcached->set($key, 1, 5);
//             $hit = $this->memcached->get($key) === 1;
//             $this->memcached->delete($key);
//
//             return $hit
//                 ? HealthResult::pass(name: 'memcache')
//                 : HealthResult::warn(name: 'memcache', message: 'write/read mismatch');
//         } catch (\Throwable $e) {
//             return HealthResult::fail(name: 'memcache', message: $e->getMessage());
//         }
//     }
// }

// --- SiteHttpCheck -------------------------------------------------------
//
// Checks that the site returns a 2xx response.
// Uses psr/http-client (PSR-18) — inject any implementation (guzzle, symfony, etc).
//
// final readonly class SiteHttpCheck implements HealthCheck
// {
//     public function __construct(
//         private \Psr\Http\Client\ClientInterface $httpClient,
//         private \Psr\Http\Message\RequestFactoryInterface $requestFactory,
//         private string $url,
//     ) {}
//
//     public function name(): string { return 'site'; }
//
//     public function check(): HealthResult
//     {
//         try {
//             $request = $this->requestFactory->createRequest('GET', $this->url);
//             $response = $this->httpClient->sendRequest($request);
//             $code = $response->getStatusCode();
//
//             if ($code >= 200 && $code < 300) {
//                 return HealthResult::pass(name: 'site', data: ['http_code' => $code]);
//             }
//
//             return HealthResult::fail(name: 'site', message: "HTTP {$code}");
//         } catch (\Throwable $e) {
//             return HealthResult::fail(name: 'site', message: $e->getMessage());
//         }
//     }
// }


// =========================================================================
// 2. config/di.php
// =========================================================================
//
// use App\Infrastructure\Health\DatabaseHealthCheck;
// use App\Infrastructure\Health\MemcacheHealthCheck;
// use App\Infrastructure\Health\RedisHealthCheck;
// use App\Infrastructure\Health\SiteHttpCheck;
// use Rasuvaeff\Yii3HealthCheck\HealthChecker;
// use Yiisoft\Definitions\Reference;
//
// return [
//     SiteHttpCheck::class => [
//         '__construct()' => ['url' => 'https://example.com/'],
//     ],
//
//     HealthChecker::class => [
//         '__construct()' => [
//             'checks' => [
//                 Reference::to(DatabaseHealthCheck::class),
//                 Reference::to(RedisHealthCheck::class),
//                 Reference::to(MemcacheHealthCheck::class),
//                 Reference::to(SiteHttpCheck::class),
//             ],
//             'warnThresholdMs' => $params['rasuvaeff/yii3-health-check']['warnThresholdMs'],
//         ],
//     ],
// ];


// =========================================================================
// 3. config/routes.php
// =========================================================================
//
// use Rasuvaeff\Yii3HealthCheck\HealthEndpoint;
// use Rasuvaeff\Yii3HealthCheck\LivenessEndpoint;
// use Rasuvaeff\Yii3HealthCheck\ReadinessEndpoint;
// use Yiisoft\Router\Route;
//
// return [
//     // "Is the process alive?" — no external checks, never fails unless process is dead.
//     // k8s livenessProbe: failure → container restart.
//     Route::get('/live')->action(LivenessEndpoint::class)->name('health.live'),
//
//     // "Is the app ready to serve traffic?" — runs all registered HealthCheck implementations.
//     // k8s readinessProbe: failure → removed from load balancer, no restart.
//     Route::get('/ready')->action(ReadinessEndpoint::class)->name('health.ready'),
//
//     // Full status for dashboards/monitoring/alerting.
//     Route::get('/health')->action(HealthEndpoint::class)->name('health.full'),
// ];


// =========================================================================
// 4. Kubernetes deployment.yaml (probe section)
// =========================================================================
//
// livenessProbe:
//   httpGet:
//     path: /live
//     port: 8080
//   initialDelaySeconds: 5
//   periodSeconds: 10
//   failureThreshold: 3
//
// readinessProbe:
//   httpGet:
//     path: /ready
//     port: 8080
//   initialDelaySeconds: 5
//   periodSeconds: 10
//   failureThreshold: 3


// =========================================================================
// 5. Example JSON responses
// =========================================================================
//
// GET /live  → 200
// {"status":"pass","message":"alive"}
//
//
// GET /ready  → 200  (all healthy)
// {
//   "status": "pass",
//   "checks": {
//     "database": {"status": "pass", "message": "", "elapsedMs": 2.14},
//     "redis":    {"status": "pass", "message": "", "elapsedMs": 0.31},
//     "memcache": {"status": "pass", "message": "", "elapsedMs": 0.28},
//     "site":     {"status": "pass", "message": "", "data": {"http_code": 200}, "elapsedMs": 87.5}
//   }
// }
//
//
// GET /ready  → 503  (database down)
// {
//   "status": "fail",
//   "checks": {
//     "database": {"status": "fail", "message": "SQLSTATE[HY000]: Connection refused", "elapsedMs": 5001.4},
//     "redis":    {"status": "pass", "message": "", "elapsedMs": 0.30},
//     "memcache": {"status": "pass", "message": "", "elapsedMs": 0.27},
//     "site":     {"status": "pass", "message": "", "data": {"http_code": 200}, "elapsedMs": 91.1}
//   }
// }
//
//
// GET /ready  → 200  (DB slow, warn threshold 1000ms exceeded)
// {
//   "status": "warn",
//   "checks": {
//     "database": {"status": "warn", "message": "Check took 1243.0ms (threshold: 1000.0ms)", "elapsedMs": 1243.0},
//     "redis":    {"status": "pass", "message": "", "elapsedMs": 0.30},
//     "memcache": {"status": "pass", "message": "", "elapsedMs": 0.27},
//     "site":     {"status": "pass", "message": "", "data": {"http_code": 200}, "elapsedMs": 91.1}
//   }
// }
