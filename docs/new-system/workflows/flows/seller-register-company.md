# Flow B — Seller registers a company

**In one sentence:** The Seller adds a company; if it is new, it is available right away — **no approval**. If the same company already exists, it is **not** added again.

## Who is involved

| Role | What they do |
|------|----------------|
| Seller | Registers the company (with duplicate check) |
| Partner | Can see the company once it is successfully created |
| Admin | Can see companies (monitor only) |

## Flowchart

```mermaid
flowchart TD
  Login[Seller logs in] --> Check{Same company already exists?}
  Check -->|Yes| Stop[Do not add — company already exists]
  Check -->|No| Create[Seller registers company]
  Create --> Live[Company is live immediately — no approval]
  Live --> Partners[Partners can see the company]
```

## Steps

1. **What happens:** Seller logs into their workspace.  
   **Result:** Seller is authenticated.

2. **What happens:** System checks whether the **same company already exists** (for example same identity such as CIN / known company record — as implemented).  
   **Result:**  
   - If it **already exists** → **do not add** a new company; seller is told it already exists.  
   - If it is **new** → continue to create.

3. **What happens:** Seller submits company details for a **new** company.  
   **Result:** Company is created and is **live immediately**. There is **no approval step**.

4. **What happens:** Partners can discover the company on business lists/homes.  
   **Result:** Marketplace inventory includes this company without waiting on admin approval.

## Rules to remember

| Rule | Meaning |
|------|---------|
| **No approval** | Seller registration does **not** wait for admin approve to go live. |
| **Duplicate check** | If the same company already exists, **do not create** another copy. |

## When this flow ends

Either the company is **live** for partners, or creation was **blocked** because the company already exists.

## Next flow

→ [Seller sets prices and deals](seller-prices-and-deals.md)

## Related

- [Whole project flow](../whole-project-flow.md)  
- Previous: [Wealth Manager creates Seller](wm-create-seller-distributor.md)  
- Note: [Company goes live](company-goes-live.md) — no longer a separate approval step

---

## For technical team

**Target product rule (these docs):** create without approval gate; enforce duplicate-company validation before insert.  
**Current code:** seller and Institution company create save `approval_status=approved` and `status=0`, so the company is live for partners immediately. Duplicate CIN / legal name checks still block a second copy.
