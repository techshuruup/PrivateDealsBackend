# Role–permission table

**Confirmed** rules only unless marked **Provisional** or **Unconfirmed**.

---

## 1. User creation

| Actor | Can create | Cannot create |
|-------|------------|----------------|
| **Admin** | Wealth Manager, Seller, Distributor, Retailer, Institution | Investor, Relationship Manager |
| **Wealth Manager** | Investor, Relationship Manager, Distributor, Retailer | Seller |
| **Distributor** | Retailer, Investor, Relationship Manager | Seller, Wealth Manager |
| **Retailer** | Investor | Other partners, RM, Seller |
| **Relationship Manager** | — | Users and Investors |
| **Seller** | — (no user accounts) | All user/role accounts |

**Notes (confirmed)**
- **Institution** is an implemented partner type. Admin creates it with the same fields as Distributor, including an optional Wealth Manager parent and required CML KYC saved on the self investor. The partner portal and partner API cannot create it. After login, Institution can create a company (`unlisted` or `secondary`) that is approved and live for partners immediately (`approval_status=approved`, `status=0`), then list the catalog, read detail, list its submissions, replace promoters and shareholders, and manage deals. Seller share-price quotes stay on the seller app.
- Admin create for Wealth Manager, Distributor, Retailer, and Institution includes CML KYC on the self investor. Relation Manager create does not include CML and does not create a self investor.
- Admin-created Distributors and Retailers may exist **independently** (parent not mandatory).
- Seller may view/interact with relevant users during business processes but **cannot create or manage** their accounts.
- Distinguish: **creates a user** vs **is the user’s parent** vs **manages assigned Investors**.

---

## 2. Investor assignment to Relationship Manager

| Actor | Assign / reassign Investors to RM |
|-------|-------------------------------------|
| Wealth Manager | **Yes** |
| Distributor | **Yes** |
| Retailer | **Unconfirmed** — do not assume |
| Relationship Manager | Manages **assigned** Investors only; does not create Investors |
| Admin | View all data; Investor/RM creation out of current Admin scope |
| Seller | No account management |

---

## 3. Investment-area access (not roles)

Areas:
1. **Primary Startup** — Private Equity  
2. **Secondary Startup** — LP Secondary  
3. **PRE-IPO** — Unlisted Shares  

| Rule | Status |
|------|--------|
| WM can have one, two, or all three areas | Confirmed |
| Admin assigns the Wealth Manager’s areas | Confirmed |
| Parent may grant subordinate all or **subset** of parent’s enabled areas | Confirmed |
| Parent cannot grant an area it does not have | Confirmed |
| Applies through the subordinate hierarchy | Confirmed |
| Removing an area from parent removes it from all affected subordinates | **Provisional** |
| Access setup for Admin-created independent Dist/Retailer | **Unconfirmed** |
| Investor-specific access behaviour | **Unconfirmed** |

**Example (confirmed):** WM has all three areas → creates Distributor with only Unlisted Shares → that Distributor may grant Retailers only Unlisted Shares.

---

## 4. Application access after login

| App | After successful login |
|-----|------------------------|
| Wealth Manager app | Always show **investment-area selection** (even if only one area enabled); user may enter only authorised areas |
| Private Deal Seller app | Go to Seller **dashboard** |
| Onboarding / profile / KYC before access | **Not required** in this proposed flow |
| Mandatory password change | **Not** in this proposed flow |

Login: mobile number + password. Outcomes: success, invalid credentials, blocked/deleted account.

---

## 5. Company, deal, share price

| Action | Who | Confirmed rules |
|--------|-----|-----------------|
| Create company | Admin, Seller | Shared company records across apps |
| Duplicate company | Seller | Must use existing record; cannot create duplicate |
| Edit company | Seller | Only companies **originally created by that Seller**; not Admin’s or another Seller’s |
| Create deal | Seller | Associated with a company; appears in Wealth Manager **with no Admin approval** |
| Update share price | Seller | Allowed at high level; eligibility/ownership/effects **Unconfirmed** |
| View all data | Admin | Yes (external Admin) |

Deal visibility beyond “available in WM” and “area access ≠ every deal” — **Unconfirmed**.

---

## 6. Quick matrix (create users)

|  | WM | Seller | Dist | Retailer | RM | Investor |
|--|----|--------|------|----------|-----|----------|
| Admin | Create | Create | Create | Create | — | — |
| WM | — | — | Create | Create | Create | Create |
| Distributor | — | — | — | Create | Create | Create |
| Retailer | — | — | — | — | — | Create |
| RM | — | — | — | — | — | — |
| Seller | — | — | — | — | — | — |

---

See also: [assumptions-and-questions.md](assumptions-and-questions.md)
