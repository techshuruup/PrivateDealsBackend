# START HERE — Partner marketplace (new system)

**Open this file first** for the product story.  
**Business diagrams:** → **[business-diagrams/README.md](business-diagrams/README.md)**  
**Transaction diagrams (buy flow):** → **[business-diagrams/transaction/README.md](business-diagrams/transaction/README.md)**

---

## Transaction diagrams (buy mandate → complete)

| Item | Open |
|------|------|
| **Transaction index** | [business-diagrams/transaction/README.md](business-diagrams/transaction/README.md) |
| Use case | [business-diagrams/transaction/use-case.md](business-diagrams/transaction/use-case.md) |
| Activity | [business-diagrams/transaction/activity.md](business-diagrams/transaction/activity.md) |
| Swimlane activity | [business-diagrams/transaction/swimlane.md](business-diagrams/transaction/swimlane.md) |

Investor signs mandate + deal slip and pays offline; Partner uploads payment receipt and confirms; Seller accepts/rejects and handles share transfer.

---

## Two applications

| Application | Who |
|-------------|-----|
| **Wealth Manager** app | WM, Distributor, Retailer, Relationship Manager |
| **Institution** (partner type) | Admin-created account on `partner.type`. Same create form as Distributor. Can create an unlisted or secondary company that is approved and live for partners immediately, and can use the Institution company catalog, submissions, promoters, shareholders, and deals APIs. Seller share-price quotes stay on the seller app. This is not the seller-app label in the transaction diagrams. |
| **Private Deal Seller** app | Seller only (no subordinate users) |
| **Admin** | External app (outside this project scope); can view all data |

---

## Business diagrams pack

| Item | Open |
|------|------|
| Diagrams index | [business-diagrams/README.md](business-diagrams/README.md) |
| Role–permission table | [business-diagrams/role-permissions.md](business-diagrams/role-permissions.md) |
| Assumptions & questions | [business-diagrams/assumptions-and-questions.md](business-diagrams/assumptions-and-questions.md) |
| Use cases — Admin | [business-diagrams/use-cases/admin.md](business-diagrams/use-cases/admin.md) |
| Use cases — WM family | [business-diagrams/use-cases/wealth-manager.md](business-diagrams/use-cases/wealth-manager.md) |
| Use cases — Seller | [business-diagrams/use-cases/seller.md](business-diagrams/use-cases/seller.md) |
| Swimlanes A–F | [business-diagrams/swimlanes/](business-diagrams/swimlanes/) |
| **Transaction diagrams** | [business-diagrams/transaction/README.md](business-diagrams/transaction/README.md) |
| PlantUML sources | [business-diagrams/plantuml/](business-diagrams/plantuml/) |

Also: older Mermaid pack under [diagrams/](diagrams/) (superseded for stakeholder reviews by **business-diagrams**).

---

## Whole story (high level)

```mermaid
flowchart TD
  Admin[Admin creates WM Seller Dist Retailer] --> Login[Users log in]
  Login --> WMApp[WM app — area selection]
  Login --> SellerApp[Seller app — dashboard]
  SellerApp --> Co[Create or use existing company]
  Co --> Deal[Create deal — live in WM no approval]
  Co --> Price[Update share price — high level]
  WMApp --> Users[Create Investors RM Dist Retailer as allowed]
  Users --> Assign[WM or Dist assign Investors to RM]
  WMApp --> Invest[Invest in authorised areas]
```

---

## Hierarchy (confirmed)

```text
Admin (external)
├── Wealth Manager — areas assigned by Admin
│     ├── Relationship Manager — assigned Investors only
│     ├── Investors
│     ├── Distributor — subset of WM areas
│     │     ├── Relationship Manager
│     │     ├── Investors
│     │     └── Retailers → Investors
│     └── Retailer → Investors
├── Seller — no users below
├── Distributor — may be independent (no parent)
└── Retailer — may be independent (no parent)
```

| Rule | Status |
|------|--------|
| WM cannot create Seller | Confirmed |
| Seller cannot create users | Confirmed |
| Admin does not create Investors or RMs | Confirmed |
| Parent grants only areas it has | Confirmed |
| Cascade revoke of areas to subordinates | **Provisional** |

Full detail: [workflows/flows/wm-create-seller-distributor.md](workflows/flows/wm-create-seller-distributor.md)

---

## Investment areas (access, not roles)

1. Primary Startup — Private Equity  
2. Secondary Startup — LP Secondary  
3. PRE-IPO — Unlisted Shares  

---

## Also useful

- [Actors](actors/README.md)  
- [Partner module](modules/partner-business.md)  
- [Database](database/partner.md)  
- [Docs index](../README.md)  
