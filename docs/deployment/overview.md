# Deployment Overview

## Runtime requirements

- PHP 8.2+ (README suggests 8.3)
- MySQL
- Composer dependencies
- Web server document root = `public/`
- Cron: `* * * * * php artisan schedule:run`
- Queue worker: `php artisan queue:work` (database driver)

## Active schedule (from `routes/console.php`)

| Command | Cadence |
|---------|---------|
| `telescope:prune` | daily |
| `backup:run --only-db` | daily |
| `send:renewal-reminder` | daily 11:00 |
| `reminder:private-equity-share-price-update` | weekdays 10:30 |
| `app:dispatch-whats-app-messages` | every 2 min |
| `app:dispatch-push-notifications` | every 2 min |
| `app:company-share-price-update-today-change` | daily 17:00 |
| `app:company-share-price-data-update` | daily 10:30 |
| `app:dispatch-preipo-transaction-reminder-message` | daily 11:30 |
| `app:send-pending-kyc-reminders` | hourly |
| `calendly:sync` | every minute |

Commented Pre-IPO reminder/escalation/auto-cancel schedules exist — enable only with product/ops agreement.

## GitHub Actions deploy

A push to `main` runs [`.github/workflows/deploy.yml`](../../.github/workflows/deploy.yml). GitHub SSHs to the VPS and, in `/home/privatedeals-web/htdocs/privatedeals.in/backend`:

1. `git pull origin main`
2. `composer install --no-dev --optimize-autoloader`
3. `php artisan migrate --force` (pending migrations only)
4. `php artisan optimize`

Repo secrets, not stored in the workflow file: `VPS_HOST`, `VPS_USER`, `VPS_SSH_KEY`, `VPS_PORT`. The server `.env` stays on the VPS. Queue workers and cron are not restarted by this workflow.

## Deploy checklist (typical)

1. `composer install --no-dev` (or with dev as appropriate)
2. Set `.env` (APP_KEY, DB, AWS, integrations)
3. `php artisan migrate --force`
4. `php artisan optimize` / config/route/view cache as needed
5. Restart queue workers
6. Confirm cron + storage permissions + S3 credentials
7. Smoke-test: admin login, API headtoken, one invest read endpoint, webhook health

## Storage

- Ensure `storage/` and `bootstrap/cache` writable
- If S3: validate `AWS_SCHEME` matches HTTPS termination

## Observability

- Telescope (disable or restrict on production)
- `api_log` table grows with traffic — plan retention

## Related

- [configuration/environment.md](../configuration/environment.md)
- [troubleshooting/common-issues.md](../troubleshooting/common-issues.md)
