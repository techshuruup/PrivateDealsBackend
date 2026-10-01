# Integrations Overview

| Integration | Used for | Key code / config |
|-------------|----------|-------------------|
| **Laravel Sanctum** | API user tokens | guards in `config/auth.php` |
| **AWS S3** | File storage | `FILESYSTEM_DISK=s3`, Flysystem S3 |
| **Digio** | eSign / KYC PAN verify / documents | `DigioHelper`, digio webhooks |
| **WhatsApp (11za)** | Messaging + inbound webhook. Outbound send is OTP only (`otp_verification_sec`) until `WhatsAppSendTrait::WHATSAPP_ALLOWED_TEMPLATES` is widened. | `WhatsAppMessagesHelper`, jobs, `webhook/11za` |
| **Firebase / FCM** | Push notifications | `FCMService`, device token models, Google auth token model |
| **Google Sign-In** | Investor V2 auth | `GOOGLE_CLIENT_*`, AuthController |
| **Calendly** | Consultancy booking | env `CALENDLY_*`, webhook, `calendly:sync` |
| **OCR.Space** | Document/price OCR | `OCR_SPACE_API_KEY`, admin company + forge parsers |
| **DomPDF** | PDF generation | `barryvdh/laravel-dompdf` |
| **Maatwebsite Excel** | Imports/exports | admin exports, cap table upload |
| **Spatie Backup** | DB backup schedule | `backup:run --only-db` |
| **Spatie Permission** | Admin permissions | `hasPermission` middleware |
| **Telescope** | Request/job debugging | prune daily |
| **Chunk upload** | Large uploads | `pion/laravel-chunk-upload` |
| **PHPMailer** | Mail sending paths | package present; also Laravel mail |
| **BSE holiday calendar** | Business days | `BseHolidayModel`, helpers, master API |

## Integration change rules

1. Update this doc + the feature doc that depends on the integration.
2. Webhook signature/auth changes need coordinated deploy with provider dashboard.
3. Never commit live secrets; rotate any secrets found in historical README examples.

## Related

- [api/webhooks-third-party.md](../api/webhooks-third-party.md)
- [features/notifications-comms.md](../features/notifications-comms.md)
- [features/kyc-demat.md](../features/kyc-demat.md)
