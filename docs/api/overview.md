# API Overview

## Base

Laravel registers `routes/api.php` with default `/api` prefix.

## Version map

```mermaid
flowchart TB
  head[ApiHeaderAuthMiddleware headtoken]
  head --> v1[v1 investor / business / startup]
  head --> v2[v2 investor / business / seller]
  head --> master[master / get-config]
  head --> forge[forge OCR]

  webhooks[Webhooks no headtoken]
  sandbox[sandbox ThirdParty auth]
  sandbox --> external[sandbox/external startups]
  sandbox --> ai[sandbox/ai company ingest]
```

## Controllers map

| Area | Namespace |
|------|-----------|
| V1 Investor | `Api\V1\Investor\` |
| V1 Business | `Api\V1\Business\` |
| V1 Startup | `Api\V1\Startup\` |
| V2 Investor | `Api\V2\Investor\` |
| V2 Business | `Api\V2\Business\` |
| V2 Seller | `Api\V2\Seller\` |
| Master / config | `Api\MasterController`, `Api\ConfigController` |
| Upload/OCR | `Api\UploadAndParseController` |
| Guest FCM | `Api\GuestDeviceTokenController` |
| Third party | `ThirdParty\StartupController`, `ThirdParty\AiCompanyIngestController` |
| Webhooks | `WebhookController` |
| PrivateDeals form | `Api\PrivateDealsController` (open, no headtoken) |

## Documentation split

- [v1.md](v1.md) — V1 surfaces
- [v2.md](v2.md) — V2 surfaces
- [app-handoff.md](app-handoff.md) — short start-here for the app team (Hoppscotch collection + what to build)
- [institution.md](institution.md) — Institution partner company submit and deals
- [partner.md](partner.md) — Partner investor list, investor create, CML KYC, and `self_investor_id` on existing login and profile
- [webhooks-third-party.md](webhooks-third-party.md)
- Focused Pre-IPO payload notes: [preipo-v2-unlisted-secondary-api-changes.md](../preipo-v2-unlisted-secondary-api-changes.md)

## Conventions for changes

1. Prefer additive JSON fields.
2. Mirror partner/business endpoints when investor behavior changes on shared methods.
3. Update feature + API docs in the same PR/task.
4. Record breaking changes explicitly in the relevant feature doc.
