# Feature: KYC, Demat & Bank

## Purpose

Collect and verify investor identity (PAN/Aadhaar), demat (CML PDF), and bank account details; support Digio eKYC and admin manual review.

## Entry points

| Path | Notes |
|------|-------|
| V1 `/investor/kyc/*` | Broad KYC get/post, eKYC token/data, uploads |
| V1 `/investor/kyc/upload/*` | `CommonKycController` demat-bank, aadhar-pan, pan, bank |
| V2 `/investor/kyc/upload/demat-bank` | Slimmer V2 surface |
| V2 `/investor/kyc/verification/pan` | `DigioHelper::verifyPan` |
| V2 `/forge/read/demat-pdf` | PDF parse |
| V2 `/api/v2/business/investor/kyc/cml/read`, `/api/v2/business/investor/kyc/cml/save` | Partner KYC for an owned investor (`investor_id`). Same parse and save as investor CML. See [api/v2.md](../api/v2.md) and [api/partner.md](../api/partner.md). |
| Global forge (auth investor) | `/api/forge/read/demat-pdf`, `aadhar-pan` via `UploadAndParseController` |
| Admin | manual KYC / AIF onboard routes. Admin create for Wealth Manager, Distributor, Retailer, and Institution reuses `DematKycService::saveDematKyc` on the partner’s self investor. After a successful save, both `partner.name` and the self investor’s `name` are the KYC full name. Relation Manager create does not. |

## Services

- `DematPdfParsingService`, `DematKycService`
- OCR.Space via env `OCR_SPACE_API_KEY` (also used in admin company flows)

## Models

- `InvestorKycModel`, `InvestorKycPanModel`, `InvestorKycAadharModel`
- `InvestorDematAccountModel`, `TempDematCmlFiles`, `DematManualModel`
- `UserBankAccountModel`, `BankDetailsModel`
- `InvestorAifKycModel`, `TempAadharPanDetailsModel`
- `KycHistoryModel`

## Jobs

- `SendAadharPanNotificationJob`, `SendPendingKycAdminNotification`
- `KycCompletedBroadcastJob`, `KycManualReviewRequiredJob`
- Command: `SendPendingKycReminders` (hourly schedule)

## Business rules

- KYC completeness often gates investing — do not remove checks without product approval.
- Digio PAN verification failures must surface clear API errors.
- Manual admin review path must stay available when automatic verification fails.

## Risks

- PII storage — be careful with logging (`ApiLogModel` may store params).
- PDF parsing heuristics are brittle; golden-file tests recommended if changing parsers.

## Related

- [integrations/overview.md](../integrations/overview.md)
- [workflows/investor-onboarding.md](../workflows/investor-onboarding.md)
