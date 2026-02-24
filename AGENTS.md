# AGENTS.md

## Cursor Cloud specific instructions

### Project overview

Laravel 12 API backend serving a Next.js frontend (dogeow). Uses SQLite for local dev, MySQL 8 + Redis 7 in production. See `.cursorrules` and `.cursor/rules/laravel-boost.mdc` for detailed coding conventions.

### System dependencies (pre-installed in VM snapshot)

- PHP 8.4 with extensions: sqlite3, imagick, redis, mysql, gd, bcmath, intl, mbstring, xml, curl, zip
- Composer 2.x
- Node.js 22.x (for Vite asset building)

### Environment setup

After `composer install` and `npm install`, the app needs:
1. `.env` file (copy from `.env.example` if missing)
2. `APP_KEY` generated (`php artisan key:generate`)
3. SQLite database created (`touch database/database.sqlite`)
4. `SESSION_CONNECTION=sqlite` appended to `.env` — the `config/session.php` defaults `SESSION_CONNECTION` to `sessions`, which refers to a Redis connection. Without this override, `php artisan serve` returns 500 on every request.
5. Migrations run (`php artisan migrate`)

### Running the dev server

```
php artisan serve --host=0.0.0.0 --port=8000
```

Full dev mode (server + queue + reverb): `composer run dev` (see `composer.json` scripts).

### Lint, test, build

| Task | Command |
|------|---------|
| Lint (check) | `vendor/bin/pint --test` |
| Lint (fix) | `vendor/bin/pint --dirty` |
| Tests | `php artisan test` |
| Static analysis | `vendor/bin/phpstan analyse` |
| Build assets | `npm run build` |
| Dev assets (HMR) | `npm run dev` |

### Gotchas

- **`SESSION_CONNECTION` must be set to `sqlite`** in `.env` for local dev. Without this, every HTTP request fails with `Database connection [sessions] not configured` because the default `sessions` connection only exists under `database.redis`, not `database.connections`.
- The `composer.lock` may lag behind `composer.json` — if `composer install` warns about the lock file, run `composer update` to sync.
- `node_modules` may need a clean reinstall (`rm -rf node_modules package-lock.json && npm install`) if the Rollup native module error appears during `npm run build`.
- Tests use in-memory SQLite (`:memory:`) per `phpunit.xml` — no database file needed for tests.
- Some tests are pre-existing failures (e.g. `CleanupDisconnectedChat*`, `FixItemImagePaths*`, `WebSocketAuth*`). These are not caused by environment issues.
