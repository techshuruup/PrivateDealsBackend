# Request Lifecycle

## Web (browser)

1. Request hits `public/index.php` → Laravel bootstrap (`bootstrap/app.php`).
2. Routing uses `routes/web.php`.
3. Common outer middleware groups (from route file):
   - `CoreMiddleware` — core theme/app setup
   - `isUserMiddleware` / `isMaintenanceMiddleware` — user presence / maintenance gate
4. Front public pages under `front.*` names → `Web\Front\WebsiteController` etc.
5. Admin under `/admin` → session `admin` guard + `hasPermission:*` where configured.
6. Investor / partner / startup web areas use dedicated redirect-if-(not)-authenticated middleware classes under `app/Http/Middleware/`.

Sitemap: `sitemap.xml` via `MarketingPageController`.

---

## API (mobile / integrations)

1. Most authenticated product APIs are wrapped in:

```text
Route::group(['middleware' => [ApiHeaderAuthMiddleware::class]], function () { ... })
```

2. `ApiHeaderAuthMiddleware`:
   - Requires header `headtoken`
   - Validates against `ApiTokenForHeaderAuthModel` (`is_deleted = 0`)
   - On failure returns JSON `Unauthorized Request` with **HTTP 500** (not 401) — clients must handle this
   - Logs request metadata to `ApiLogModel` unless header `isdebug` is truthy

3. Inside the group, nested `auth:investor-api-guard` / `partner-api-guard` / `startup-api-guard` apply Sanctum.

4. Outside the headtoken group (examples):
   - Digio / WhatsApp / Calendly webhooks
   - Some test/share-price endpoints
   - Third-party sandbox (`ThirdPartyApiAuthMiddleware` + throttle)
   - Basic-auth `external` sample route

Entry map: `routes/api.php`.

---

## Artisan / schedule

`routes/console.php` registers `Schedule::command(...)` entries. Production must run:

```bash
php artisan schedule:run
```

on a cron (every minute), and a queue worker:

```bash
php artisan queue:work
```

---

## Response patterns (API)

Controllers typically return JSON envelopes with `message` / `data` / status fields (not a single shared API Resource layer). When editing responses, check **both V1 and V2** consumers and the partner/business variants that reuse investor methods.
