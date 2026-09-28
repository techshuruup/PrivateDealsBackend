# Business diagrams — Wealth Manager & Private Deal Seller

**Audience:** Internal team and business stakeholders  
**Apps:** Wealth Manager application · Private Deal Seller application  
**Admin:** External supporting actor (Admin app outside this project scope)

**Start here for the product story:** [../START-HERE.md](../START-HERE.md)

---

## Contents

| Item | Link |
|------|------|
| Role–permission table | [role-permissions.md](role-permissions.md) |
| Assumptions & open questions | [assumptions-and-questions.md](assumptions-and-questions.md) |
| Use cases — Admin | [use-cases/admin.md](use-cases/admin.md) |
| Use cases — Wealth Manager family | [use-cases/wealth-manager.md](use-cases/wealth-manager.md) |
| Use cases — Seller | [use-cases/seller.md](use-cases/seller.md) |
| Swimlane A — Admin creates account → login → access | [swimlanes/A-admin-create-and-login.md](swimlanes/A-admin-create-and-login.md) |
| Swimlane B — Create allowed user + grant access areas | [swimlanes/B-channel-create-user-and-access.md](swimlanes/B-channel-create-user-and-access.md) |
| Swimlane C — Assign investors to Relationship Manager | [swimlanes/C-assign-investors-to-rm.md](swimlanes/C-assign-investors-to-rm.md) |
| Swimlane D — Create company | [swimlanes/D-company-create.md](swimlanes/D-company-create.md) |
| Swimlane E — Seller creates deal → available in WM | [swimlanes/E-seller-create-deal.md](swimlanes/E-seller-create-deal.md) |
| Swimlane F — Seller updates share price | [swimlanes/F-seller-update-share-price.md](swimlanes/F-seller-update-share-price.md) |
| **Transaction (buy) — use case / activity / swimlane** | [transaction/README.md](transaction/README.md) |
| Editable PlantUML sources | [plantuml/](plantuml/) |

Markdown pages include **Mermaid** for viewing in GitHub/Cursor. Matching **`.puml`** files are editable PlantUML sources.

---

## Confirmed naming

- **Relationship Manager** (not “Relation Manager”)
- Investment areas (access, not roles): **Primary Startup (Private Equity)**, **Secondary Startup (LP Secondary)**, **PRE-IPO (Unlisted Shares)**
