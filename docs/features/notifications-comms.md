# Feature: Notifications & Communications

## Purpose

Deliver WhatsApp, push (FCM), email/SMS, and in-app notifications for KYC, transactions, broadcasts, and reminders.

## Channels

| Channel | Key code | Persistence |
|---------|----------|-------------|
| WhatsApp | `WhatsAppMessagesHelper`, Jobs under `Jobs/Whatsapp`, webhook `webhook/11za`. OTP verification uses template `otp_verification_sec` (`UtillsHelper::sendVerificationCode`) | `ReportMessagesWhatsappModel`, replies, broadcasts |
| Push | `FCMService`, `FirebasePushNotificationSendJob`, `PushNotificationJob` | device tokens `CoreFirebaseDeviceTokenModel`, `NotificationsModel`, broadcast models |
| Email/SMS | mail config, `SMSHelper`, report email/SMS models | `ReportMessagesEmailModel`, `ReportMessagesSMSModel` |
| In-app | notification list APIs | `NotificationsModel`, `BroadcastNotificationModel` |

## Schedulers

- `app:dispatch-whats-app-messages` every 2 minutes
- `app:dispatch-push-notifications` every 2 minutes
- KYC reminders hourly; renewal reminder daily; Pre-IPO reminder message daily

## Admin

- WhatsApp broadcast UI + push notification UI under `hasPermission:broadcast`
- Guest device token: `POST /api/guest/device-token`
- Open form email: `POST /api/privatedeals/submit-data` → `privatedeals.in@gmail.com` (`PMailerTrait`)

## Risks

- Dispatchers every 2 minutes require a healthy queue worker.
- Template/provider changes must stay compatible with webhook reply handling.
- Do not log full message PII unnecessarily.

## Related

- [integrations/overview.md](../integrations/overview.md)
- [deployment/overview.md](../deployment/overview.md)
