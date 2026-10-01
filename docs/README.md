# PrivateDeals V1 — Documentation Index

**Living source of truth** for humans and AI coding agents.  
Source code always wins if docs and code disagree — update the docs.

---

## How to use this documentation

```text
Project Overview
    ↓
Architecture
    ↓
Modules / Features
    ↓
Database + APIs + Workflows
    ↓
Auth / Config / Integrations
    ↓
Development / Deployment / Troubleshooting
```

Before changing code: read relevant docs → implement → **documentation impact check** → update docs if behavior changed.  
See [maintenance/living-docs-rules.md](maintenance/living-docs-rules.md).

---

## Navigation

### New system (target partner marketplace)
| Document | Purpose |
|----------|---------|
| [**new-system/START-HERE.md**](new-system/START-HERE.md) | **Open this first** — whole story |
| [**new-system/business-diagrams/README.md**](new-system/business-diagrams/README.md) | **Use cases · Swimlanes A–F · Permissions · PlantUML** |
| [**new-system/business-diagrams/transaction/README.md**](new-system/business-diagrams/transaction/README.md) | **Transaction buy flow** — use case · activity · swimlane |
| [new-system/business-diagrams/role-permissions.md](new-system/business-diagrams/role-permissions.md) | Role–permission table |
| [new-system/diagrams/README.md](new-system/diagrams/README.md) | Older Mermaid diagram pack |
| [new-system/actors/README.md](new-system/actors/README.md) | Who is who |
| [new-system/database/partner.md](new-system/database/partner.md) | Partner schema (target) |
| [new-system/modules/partner-business.md](new-system/modules/partner-business.md) | Partner module (target) |
| [new-system/workflows/](new-system/workflows/) | Whole flow + topic detail pages |

Pointer stub: [START-HERE.md](START-HERE.md) → redirects to new-system.

### Start here (engineering)
| Document | Purpose |
|----------|---------|
| [documentation-plan.md](documentation-plan.md) | Approved analysis plan that produced this tree |
| [project-overview.md](project-overview.md) | What PrivateDeals is, actors, product domains |
| [architecture/overview.md](architecture/overview.md) | System shape, layers, major dependencies |
| [architecture/request-lifecycle.md](architecture/request-lifecycle.md) | How web/API requests flow |
| [architecture/cross-cutting-risks.md](architecture/cross-cutting-risks.md) | Coupling, fat controllers, change risks |

### Modules (by actor / surface)
| Document | Purpose |
|----------|---------|
| [modules/admin.md](modules/admin.md) | Admin portal (`/admin`) |
| [modules/investor.md](modules/investor.md) | Investor web + API |
| [modules/partner-business.md](modules/partner-business.md) | Pointer → new-system partner module |
| [modules/startup.md](modules/startup.md) | Startup portal + API |
| [modules/public-website.md](modules/public-website.md) | Public marketing site (migrated Blade site) |
| [modules/shared-helpers-services.md](modules/shared-helpers-services.md) | Helpers, services, repositories |

### Features
| Document | Purpose |
|----------|---------|
| [features/pre-ipo.md](features/pre-ipo.md) | Unlisted / Pre-IPO trading |
| [features/pre-ipo-order-notifications.md](features/pre-ipo-order-notifications.md) | Planned Pre-IPO order notification matrix (not live yet) |
| [features/primary-transactions.md](features/primary-transactions.md) | Startup primary fundraising |
| [features/secondary-market.md](features/secondary-market.md) | Secondary transfers / ROFR |
| [features/kyc-demat.md](features/kyc-demat.md) | KYC, PAN/Aadhaar, demat, bank |
| [features/portfolio.md](features/portfolio.md) | Startup + Pre-IPO portfolios |
| [features/companies-pricing.md](features/companies-pricing.md) | Company master, prices, news, deals, partner enquiries |
| [features/ai-autowork.md](features/ai-autowork.md) | AI AutoWork hub (staging → admin approve) |
| [features/ai-autowork-company-ingest.md](features/ai-autowork-company-ingest.md) | AI company ingest API + temp_company promote |
| [features/notifications-comms.md](features/notifications-comms.md) | WhatsApp, push, email |
| [features/coupons-referrals.md](features/coupons-referrals.md) | Coupons, referrals, share links |
| [features/consultancy-calendly.md](features/consultancy-calendly.md) | Calendly booking |

### Data & APIs
| Document | Purpose |
|----------|---------|
| [database/overview.md](database/overview.md) | Schema domains, key tables/models |
| [new-system/database/partner.md](new-system/database/partner.md) | New-system partner schema (target) |
| [api/overview.md](api/overview.md) | API auth layers, versioning |
| [api/v1.md](api/v1.md) | Investor / business / startup v1 |
| [api/v2.md](api/v2.md) | Investor / business v2 |
| [api/app-handoff.md](api/app-handoff.md) | App developer start here — 30 Sep 2026 partner and Institution APIs |
| [api/order-flow-handoff.md](api/order-flow-handoff.md) | App developer handoff for the new Pre-IPO order flow |
| [api/transaction-documents-handoff.md](api/transaction-documents-handoff.md) | App note: transaction `documents` list |
| [api/institution.md](api/institution.md) | Institution partner dashboard, company submit, catalog, promoters, shareholders, deals, and pre-IPO orders |
| [api/partner.md](api/partner.md) | Partner investor list, investor create, CML KYC, Pre-IPO buy, and self investor id |
| [api/webhooks-third-party.md](api/webhooks-third-party.md) | Webhooks + sandbox |

### Workflows
| Document | Purpose |
|----------|---------|
| [new-system/workflows/whole-project-flow.md](new-system/workflows/whole-project-flow.md) | **New system:** full partner marketplace flow |
| [new-system/workflows/flows/](new-system/workflows/flows/) | New-system topic detail pages |
| [workflows/investor-onboarding.md](workflows/investor-onboarding.md) | Register → MPIN → KYC |
| [workflows/pre-ipo-buy-sell.md](workflows/pre-ipo-buy-sell.md) | Buy/sell/cancel Pre-IPO |
| [workflows/pre-ipo-order-steps.md](workflows/pre-ipo-order-steps.md) | Partner order steps on `order_step` (old orders with null `order_step` still use status 0–5) |
| [workflows/primary-investment.md](workflows/primary-investment.md) | Commit → docs → payment |
| [workflows/secondary-trade.md](workflows/secondary-trade.md) | Sell request → allot → transfer |

### Cross-cutting
| Document | Purpose |
|----------|---------|
| [authentication/overview.md](authentication/overview.md) | Guards, Sanctum, headtoken, permissions |
| [configuration/environment.md](configuration/environment.md) | `.env`, settings, filesystem |
| [integrations/overview.md](integrations/overview.md) | Digio, WhatsApp, FCM, Calendly, OCR, AWS, Google |
| [deployment/overview.md](deployment/overview.md) | Run, queue, schedule, deploy notes |
| [deployment/queue-worker.md](deployment/queue-worker.md) | VPS Supervisor worker for this app (`privatedeals-worker`) |
| [development/setup.md](development/setup.md) | Local setup |
| [development/conventions.md](development/conventions.md) | Code patterns for AI/dev |
| [troubleshooting/common-issues.md](troubleshooting/common-issues.md) | Frequent failures |
| [maintenance/living-docs-rules.md](maintenance/living-docs-rules.md) | **Required** docs update process |

### Change logs / focused notes
| Document | Purpose |
|----------|---------|
| [preipo-v2-unlisted-secondary-api-changes.md](preipo-v2-unlisted-secondary-api-changes.md) | `company.type` unlisted vs secondary API notes |

---

## Quick facts

| Item | Value |
|------|--------|
| Stack | Laravel 11, PHP 8.2+, MySQL, Livewire 3, Sanctum, Spatie Permission |
| Entry routes | `routes/web.php`, `routes/api.php`, `routes/console.php` |
| Models | ~136 Eloquent models under `app/Models/` |
| Migrations | ~363 under `database/migrations/` |
| Default admin (from root README) | username `shuruup` / password `PrivateDeals@123` |

---

## AI agent checklist (every task)

1. Read this index + the feature/module docs for the area you touch.
2. Prefer Helpers (`app/Helpers/*`) and existing Enums over inventing new status strings.
3. V1 and V2 investor APIs often share models but diverge in controllers — check both.
4. Changing Pre-IPO / primary / secondary status logic affects admin web, jobs, Digio webhooks, and mobile apps.
5. Before finishing: update affected docs in the same task.
