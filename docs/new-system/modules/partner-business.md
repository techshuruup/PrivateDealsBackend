# Module: Partner / Business (new system)

**Start:** [START-HERE](../START-HERE.md) · [Business diagrams](../business-diagrams/README.md) · [Actors](../actors/README.md)

---

## Purpose

**Wealth Manager** app (channel partners + Relationship Manager) and **Private Deal Seller** app (Seller only). Admin is external.

---

## Role summary

| Role | Creates |
|------|---------|
| Admin | WM, Seller, Dist, Retailer (not Inv/RM) |
| Wealth Manager | Inv, Relationship Manager, Dist, Retailer — **not Seller** |
| Distributor | Retailer, Inv, Relationship Manager |
| Retailer | Investor |
| Relationship Manager | — (assigned Investors) |
| Seller | — (no users); companies, deals, prices |
| Institution | Nobody else yet. Admin creates the account only. Can create an unlisted or secondary company that is approved and live for partners immediately, then list the catalog, read detail, list own submissions, and replace promoters and shareholders. Seller share-price quotes and sell enquiries stay on the seller app. This Institution can view `GET /api/v2/business/institution/dashboard`. |

**Institution (in code now):** `PartnerTypeEnum::institution` (`Institution`). Admin list/create/edit/view/delete at `admin/partner/institution`, same save path as Distributor (`PartnerRepository::newDistributorSave`). Optional Wealth Manager parent. Partner API creation is rejected. After login, only `partner.type = Institution` can check duplicates and create a company (`unlisted` or `secondary`) via `POST /api/v2/business/institution/company/check-duplicate` and `POST /api/v2/business/institution/company`. The row is `approval_status=approved` and `status=0` (live for partners immediately), `submitted_by_partner_id` is the Institution partner id, and `submitted_by_seller_id` stays null. `/admin/company/pending-seller` remains for any existing pending rows. The same login can list the approved catalog, read company detail, list its own submissions, replace promoters and shareholders, and create, list, update, delete, and bulk-insert deals. Seller share-price quotes (`seller_company_share_price`) and sell enquiries stay on the seller app. This Institution can view `GET /api/v2/business/institution/dashboard`.

**Self investor (in code now):** Creating a Wealth Manager, Distributor, Retailer, or Institution also inserts one investor for that partner (`investor.is_self = 1`, same name, mobile, and email, `partner_id` set). That row is for the partner’s own orders and is hidden from partner client lists and admin investor lists. Partner login and profile include `self_investor_id`. Updates do not create another row. Relation Manager does not get a self investor (`self_investor_id` is null) and uses the parent partner’s investor.

**Partner logo (in code now):** Create and edit for every partner type accept an optional `logo` file, using the same image rules as seller logo. The file is stored on `partner.profile_photo`. That includes admin screens, the partner portal channel-partner create, the partner portal profile, and `POST /api/v1/business/channel-partner`, `POST /api/v1/business/channel-partner/create`, and profile save. The partner view and partner API `profile_photo` always return an absolute URL: the uploaded file, or ui-avatars initials from `partner.name` when no logo is stored.

**Admin CML on create (in code now):** Admin create for Wealth Manager, Distributor, Retailer, and Institution includes the same CML KYC upload used on the admin investor view. `PartnerRepository::newDistributorSave()` saves that CML on the new self investor through `DematKycService::saveDematKyc` (`investor_kyc_demat`, PAN, bank when present, CML document, and `investor.preipo_kyc_status = 1`). The CML file and KYC fields are required. If they are missing or the KYC save fails, the partner insert is rolled back with the self investor. Relation Manager create has no CML block and no self investor. Partner API create does not collect CML. Edit screens do not.

See [role-permissions.md](../business-diagrams/role-permissions.md).

---

## Related

- [START-HERE](../START-HERE.md)
- [Database](../database/partner.md)
- [Hierarchy](../workflows/flows/wm-create-seller-distributor.md)
- [Current API notes](../../api/v2.md) (code may differ until migration)
