# Examples

| Script | Shows | Needs server? |
|---|---|---|
| `basic-usage.php` | Creating checks inline with CallbackHealthCheck, running checker manually | No |
| `yii3-app.php` | Full Yii3 wiring: DB, Redis, Memcache, HTTP site check, DI config, routes, k8s probes | No (docs) |

## Running

```bash
docker run --rm -v "$PWD":/app -w /app composer:2 php examples/basic-usage.php
```

`yii3-app.php` is documentation-as-code — it does not run standalone. Copy the relevant sections into your application.
