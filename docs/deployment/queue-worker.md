# Queue worker (Supervisor)

Production jobs for this backend are processed by their own Supervisor program. The VPS already runs another project under `laravel-worker`. That program stays as it is. This app needs a second program.

## Why a separate worker

Jobs use the database driver (`QUEUE_CONNECTION=database` in `.env.example`, default in `config/queue.php`). Laravel writes them to this app’s `jobs` table (queue name `default`). Failed jobs go to `failed_jobs`.

A worker only reads the app whose folder it was started in. `laravel-worker` is pointed at the other project, so `sudo supervisorctl restart laravel-worker:*` reloads that project only. It does not pick up Private Deals jobs, and a new program here does not stop the other one.

Scheduler cron is separate. Supervisor does not run `schedule:run`.

## Live `.env`

| Key | Value |
|-----|--------|
| `QUEUE_CONNECTION` | `database` |

`sync` runs the job inside the web request. Supervisor never sees those jobs.

`php artisan migrate` creates `jobs` and `failed_jobs` (`database/migrations/2024_05_27_154135_create_jobs_table.php`, `database/migrations/2024_05_27_161901_create_failed_jobs_table.php`).

## Supervisor program

Deploy path (same directory as [`.github/workflows/deploy.yml`](../../.github/workflows/deploy.yml)): `/home/privatedeals-web/htdocs/privatedeals.in/backend`.

Create `/etc/supervisor/conf.d/privatedeals-worker.conf` on the VPS:

```ini
[program:privatedeals-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /home/privatedeals-web/htdocs/privatedeals.in/backend/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
directory=/home/privatedeals-web/htdocs/privatedeals.in/backend
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=privatedeals-web
numprocs=1
redirect_stderr=true
stdout_logfile=/home/privatedeals-web/htdocs/privatedeals.in/backend/storage/logs/worker.log
stopwaitsecs=3600
```

Use the same Linux user and `php` binary as the site. If `php` is not on that user’s `PATH`, put the full binary path in `command`.

Load it once:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start privatedeals-worker:*
```

`sudo supervisorctl status` should show both programs, for example `laravel-worker:laravel-worker_00` and `privatedeals-worker:privatedeals-worker_00`.

## After each deploy

GitHub Actions pulls, installs, migrates, and runs `php artisan optimize`. It does not restart workers. Restart this program so it loads the new code:

```bash
sudo supervisorctl restart privatedeals-worker:*
```

Leave the other project on its own command:

```bash
sudo supervisorctl restart laravel-worker:*
```

## Check that jobs are moving

```bash
sudo supervisorctl status privatedeals-worker:*
tail -f /home/privatedeals-web/htdocs/privatedeals.in/backend/storage/logs/worker.log
```

Rows sitting in `jobs` with a running worker usually mean the worker is on the wrong directory, `QUEUE_CONNECTION` is not `database`, or this program was never started. Rows in `failed_jobs` are jobs the worker ran and rejected; read `storage/logs/laravel.log` and the worker log for the exception.

## Related

- [deployment/overview.md](overview.md)
- [configuration/environment.md](../configuration/environment.md)
- [troubleshooting/common-issues.md](../troubleshooting/common-issues.md)
