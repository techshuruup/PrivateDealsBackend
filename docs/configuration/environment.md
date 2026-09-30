# Configuration & Environment

## Files

| File | Role |
|------|------|
| `.env` / `.env.example` | Runtime config |
| `config/*.php` | Laravel + packages |
| `app_settings` DB via `SettingServiceProvider` | Dynamic global settings shared to views |
| `config/settings.php` | Settings-related config |
| `config/filesystems.php` | local/S3 disks |
| `config/backup.php` | Spatie backup |
| `config/google.php` | Google client extras |
| `config/permission.php` | Spatie permission |
| `config/telescope.php` | Telescope |

## Critical `.env` keys

| Key | Purpose |
|-----|---------|
| `APP_*` | Name, env, debug, URL, timezone `Asia/Kolkata` |
| `DB_*` | MySQL connection (`shuruup-v4` example DB name) |
| `SESSION_DRIVER` / `CACHE_STORE` / `QUEUE_CONNECTION` | Prefer `database` per example |
| `FILESYSTEM_DISK` | `local` or `s3` |
| `AWS_*` | S3 when used; `AWS_SCHEME` http/https |
| `MAIL_*` | Mailer (example uses `log`) |
| `TELESCOPE_ENABLED` | Debug tooling |
| `OCR_SPACE_API_KEY` | OCR |
| `SHURUUP_WHATSAPP_NUMBER`, `SHURUUP_EMAIL`, `SHURUUP_PHONE` | Contact branding |
| `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET` | Google auth |
| `CALENDLY_*` | Consultancy booking |

Digio and WhatsApp provider secrets may live in DB settings or code/env beyond `.env.example` — **search helpers before assuming**.

## Bootstrapping note

`SettingServiceProvider` loads all app settings at boot. Fresh setup: temporarily comment register/boot bodies, migrate, then restore (root `README.md`).

## App settings

Rows live in `app_settings` and are edited at `/admin/system-configuration/system-settings` (`SettingController`).

| Key | Meaning |
|-----|---------|
| `processing_fee_percentage` | Processing fee %. Configured in admin. Default 1. Min 1. Max 100. Applied automatically on new companies and deals. API clients do not send it. |

## PHP / Apache

Root README recommends PHP 8.3, raised upload/memory limits, vhost to `public/`.

## Related

- [development/setup.md](../development/setup.md)
- [deployment/overview.md](../deployment/overview.md)
