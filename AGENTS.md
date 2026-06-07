# AGENTS.md — yii3-health-check

Guidance for AI agents working on this package. Read before changing code.

## What this is

Health check endpoints for Yii3 applications. Provides `/live`, `/ready`,
`/health` PSR-15 request handlers with JSON responses. Composite checker
aggregates results from multiple health checks with elapsed time tracking.

Namespace: `Rasuvaeff\Yii3HealthCheck`.

Public API: `HealthStatus` (enum), `HealthResult` (value object),
`HealthCheck` (interface), `CallbackHealthCheck`, `HealthChecker`,
`HealthEndpoint`, `ReadinessEndpoint`, `LivenessEndpoint`.

## Golden rules

1. **Verification is mandatory.** Never claim "done" without a fresh green
   `composer build`. "Should work" does not count.
2. **No suppressions.** No `@psalm-suppress`, no baseline. Fix the root cause.
3. **Liveness must never depend on external services.** Only intrinsic app state.
4. **Preserve the public contract.** Update README + tests with any API change.

## Commands

No PHP/Composer on the host — run in Docker via the `composer:2` image.

```bash
docker run --rm -v "$PWD":/app -w /app composer:2 composer build
docker run --rm -v "$PWD":/app -w /app composer:2 composer cs:fix
docker run --rm -v "$PWD":/app -w /app composer:2 composer psalm
docker run --rm -v "$PWD":/app -w /app composer:2 composer test
```

Or with Make:

```bash
make build
make cs-fix
make psalm
make test
```

`composer.lock` is gitignored (library).

## Invariants & gotchas

- Check name regex: `/^[a-z][a-z0-9_.-]*$/`.
- `HealthStatus`: `pass`, `warn`, `fail`. Aggregation: any `fail` → `fail`,
  any `warn` (no `fail`) → `warn`, all `pass` → `pass`.
- HTTP mapping: `pass`/`warn` → 200, `fail` → 503.
- Exception in check callback → `fail` result with exception message.
- Elapsed time above `warnThresholdMs` upgrades `pass` to `warn`.
- Existing `warn`/`fail` statuses are never downgraded by elapsed time.
- Elapsed time is measured via `microtime(true)` by default, or `Psr\Clock`
  if injected.
- `toArray()` omits `elapsedMs` when 0 and `data` when empty.
- Endpoints implement PSR-15 `RequestHandlerInterface`.
- Liveness endpoint has no external checks — always returns `pass` + 200.

## When you finish

- Update `README.md` (and `examples/` if usage changed); update `CHANGELOG.md`
  when releasing.
- Re-run `composer build` and paste the output.
