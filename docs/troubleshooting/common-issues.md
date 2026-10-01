# Troubleshooting

## App won't boot / artisan fails mentioning settings

**Cause:** `SettingServiceProvider` queries `app_settings` before DB ready.  
**Fix:** Comment provider bodies → migrate → uncomment (see root README).

## API always Unauthorized Request

- Missing/invalid `headtoken` header
- Token marked `is_deleted`
- Note: response may be HTTP **500**, not 401

## Authenticated routes return unauthenticated

- Missing Sanctum Bearer token
- Wrong guard (investor vs partner)
- Token revoked on logout/delete-account

## Notifications not sending

- Queue worker not running (`QUEUE_CONNECTION=database`). This app uses Supervisor program `privatedeals-worker`, not the other project’s `laravel-worker`. See [deployment/queue-worker.md](../deployment/queue-worker.md).
- Scheduler not running (dispatch commands every 2 minutes)
- Device tokens missing (`CoreFirebaseDeviceTokenModel`)
- WhatsApp provider / template issues — check report message tables

## Digio document signed but status stuck

- Webhook URL reachable? (`/api/webhook/digio`)
- Helper `changeTransactionStatus` conditions not met (document type mismatch)
- Check `ReportWebhookLogModel` / logs

## Calendly slot not linking

- Webhook delay — retry `book-slot`
- `a1` tracking param missing
- `CALENDLY_*` env incomplete
- Sync command failing

## Share prices stale

- Daily commands failed
- External `share-price-update` webhook
- OCR import errors in admin

## Permission denied in admin

- `hasPermission:…` string mismatch
- Admin rights not assigned (`AdminRightsModel` / Spatie)

## File upload failures

- `php.ini` size limits
- S3 credentials / `AWS_SCHEME`
- Disk permissions on `storage/`

## Related

- [deployment/overview.md](../deployment/overview.md)
- [authentication/overview.md](../authentication/overview.md)
