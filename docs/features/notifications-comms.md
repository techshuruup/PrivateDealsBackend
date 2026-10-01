# Feature: Notifications & Communications

## Purpose

Deliver WhatsApp, push (FCM), email/SMS, and in-app notifications for KYC, transactions, broadcasts, and reminders.

## Channels

| Channel | Key code | Persistence |
|---------|----------|-------------|
| WhatsApp | `WhatsAppMessagesHelper`, Jobs under `Jobs/Whatsapp`, webhook `webhook/11za`. **Paused except OTP.** `WhatsAppSendTrait` sends only template `otp_verification_sec` (`UtillsHelper::sendVerificationCode`). Other templates are not queued. Rows already pending are marked failed by the dispatcher and are not sent. | `ReportMessagesWhatsappModel`, replies, broadcasts |
| Push | `FCMService`, `FirebasePushNotificationSendJob`, `PushNotificationJob` | device tokens `CoreFirebaseDeviceTokenModel`, `NotificationsModel`, broadcast models |
| Email/SMS | mail config, `SMSHelper`, report email/SMS models | `ReportMessagesEmailModel`, `ReportMessagesSMSModel` |
| In-app | notification list APIs. **Paused.** `NotificationsModel::IN_APP_NOTIFICATIONS_ENABLED` is false, so `creating` cancels every new inbox row (`UtillsHelper::sendNotification`, broadcasts, price alerts, admin create). Existing rows still list. Set the constant to true to save again. | `NotificationsModel`, `BroadcastNotificationModel` |

## Schedulers

- `app:dispatch-whats-app-messages` every 2 minutes
- `app:dispatch-push-notifications` every 2 minutes
- KYC reminders hourly; renewal reminder daily; Pre-IPO reminder message daily

## Admin

- WhatsApp broadcast UI + push notification UI under `hasPermission:broadcast`
- Guest device token: `POST /api/guest/device-token`
- Open form email: `POST /api/privatedeals/submit-data` → `privatedeals.in@gmail.com` (`PMailerTrait`)

## Risks

- Outbound WhatsApp other than OTP is paused in `WhatsAppSendTrait`. Re-enable by adding template names to `WHATSAPP_ALLOWED_TEMPLATES`.
- In-app notification inserts are paused on `NotificationsModel`. Re-enable with `IN_APP_NOTIFICATIONS_ENABLED = true`.
- Dispatchers every 2 minutes require a healthy queue worker.
- Template/provider changes must stay compatible with webhook reply handling.
- Do not log full message PII unnecessarily.

## Related

- [integrations/overview.md](../integrations/overview.md)
- [deployment/overview.md](../deployment/overview.md)
- [pre-ipo-order-notifications.md](pre-ipo-order-notifications.md) is the Pre-IPO partner order message matrix for rows with `order_step` set. Where a WhatsApp template is not already in the product, the code sends in-app only and logs that the template is not configured.
