# Partner business API

Source: `routes/api.php` prefix `v2` → `business`. Investor list and create: `App\Http\Controllers\Api\V2\Business\CommonController` (`investorList`, `investorCreate`). CML: `App\Http\Controllers\Api\V2\Business\KycController` (`readCml`, `saveCml`). Save persistence: `App\Services\DematKycService` (`saveDematKyc`). PDF parse: `App\Services\DematPdfParsingService` (`processPdf`). `self_investor_id` is attached in `App\Repositories\PartnerRepository` (`attachSelfInvestorId`) on the existing V1 login and profile methods.

Institution company submit and company deals are not on this page. Those routes are in [institution.md](institution.md).

## Auth

Investor list, investor create, and CML sit under `ApiHeaderAuthMiddleware` and `auth:partner-api-guard`.

- Header `headtoken` — app header token.
- Header `Authorization: Bearer <token>` — Sanctum token for a `PartnerModel` (`partner-api-guard`).

Any authenticated partner type can call these routes. They are not limited to `Institution`.

## Endpoints

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/api/v2/business/investor` | Investor list; this partner's self investor is first |
| POST | `/api/v2/business/investor` | Create an investor for the logged-in partner |
| POST | `/api/v2/business/investor/kyc/cml/read` | Parse a CML PDF for one owned investor |
| POST | `/api/v2/business/investor/kyc/cml/save` | Save demat KYC and set `preipo_kyc_status` = `1` |

---

### GET `/api/v2/business/investor`

Auth: partner API token.

Query parameters. `is_kyc` and `is_active` are required.

```
{
  "is_kyc": "All", // string required — All | Yes | No; Yes = preipo_kyc_status 1, No = 0
  "is_active": "All", // string required — All | Yes | No; Yes = is_active 1, No = 0
  "is_aif": "All", // string optional — All | Yes | No; omitted defaults to All; Yes = aif_status 1, No = 0
  "relation_manager_ids": "4,5" // string optional — comma-separated partner ids
}
```

Rows with `is_deleted` = `0` only.

Non-self rows (`is_self` = `0`):

- When `relation_manager_ids` is omitted, `partner_id` is this partner or a relation manager whose `parent_id` is this partner and whose `type` is `relationmanager`.
- When `relation_manager_ids` is sent, those ids replace that scope. The list does not also include this partner's other clients.

This partner's self investor (`is_self` = `1` and `partner_id` = this partner) is included when it is not deleted and matches the same `is_kyc`, `is_active`, and `is_aif` filters. That row is ordered first. `relation_manager_ids` does not change which self investor is used. A self investor that belongs to another partner is excluded.

Each item is the investor row (`password` hidden) plus `total_invested`, `no_of_startups`, `commission_earned`, `startup_list`, and `partner_details`. Loaded relations: `partner` (`id`, `name`), `portfolio` (and `portfolio.startup` `id`, `brand_name`), `city`, `state`, `country`, `kyc`. Commission uses this partner's `commission` percent.

Success (`status` `1`): message `Investor List`. `data` is the array. The first object is the self investor when one matches:

```
{
  "status": 1,
  "message": "Investor List",
  "data": [
    {
      "id": 88, // integer
      "name": "Partner Name", // string
      "partner_id": 3, // integer — this partner
      "is_self": 1, // 1 on the first row when this partner's self investor matches the filters; other rows are 0
      "preipo_kyc_status": 0, // integer
      "is_active": 1, // integer
      "aif_status": 0, // integer
      "total_invested": 0, // number — sum of portfolio investment_amount
      "no_of_startups": 0, // integer
      "commission_earned": 0, // number
      "startup_list": [], // array
      "partner_details": {
        "partner_id": 3, // integer or null
        "partner_name": "Partner Name" // string
      }
    }
  ]
}
```

Other investor columns on the row are returned with that object. They are omitted from the example.

---

### POST `/api/v2/business/investor`

Auth: partner API token. `V1 POST /api/v1/business/investor` is unchanged.

Password, address, city, and pincode are not collected. Access flags and CML are not accepted on this request.

```
{
  "investor_type": "Individual", // string required — Individual | Hindu Undivided Family | Private Limited | Public Limited | Partnership | Proprietorship | Limited Liability Partnership
  "name": "Client Name", // string required — max 255; stored as ucfirst(trim())
  "mobile_number": "9876543210", // numeric required
  "email": "client@example.com", // string optional — valid email when sent; stored as strtolower(trim()); blank or omitted stored as null
  "gender": "Male" // string optional — Male | Female | Other; stored only when sent
}
```

Saved row:

- `partner_id` is the logged-in partner. `created_by` and `updated_by` are that partner's `created_by`.
- `registration_step` is `3`. `mobile_country_code` is `91`. `referral_code` is generated. `is_self` is `0`.
- `is_primary_access`, `is_secondary_access`, and `is_preipo_access` are copied from the logged-in partner (`1` or `0`).
- Password stays null on a new row. Address, `city_id`, `state_id`, `country_id`, and `pincode` stay null. `uuid` is set by `InvestorModel` on create.

Uniqueness (`is_deleted` = `0` and `registration_step` = `3`):

- `mobile_number` must be unique in that set.
- `email` uses the same rule only when email is sent.

If a non-deleted incomplete row already exists for that `mobile_number` and `mobile_country_code` `91` with `registration_step` not `3`, that row is updated instead of inserting a second investor. The update still sets partner ownership, `registration_step` `3`, access flags, name, type, email, and gender as above. An existing password on that incomplete row is left as-is.

Success (`status` `1`): message `Investor Created`. `data` is the investor row (`password` hidden).

```
{
  "status": 1,
  "message": "Investor Created",
  "data": {
    "id": 89, // integer
    "name": "Client Name", // string
    "partner_id": 3, // integer — logged-in partner
    "investor_type": "Individual", // string
    "mobile_country_code": 91, // integer
    "mobile_number": "9876543210", // string
    "email": "client@example.com", // string or null
    "gender": "Male", // string or null
    "registration_step": 3, // integer
    "is_self": 0, // integer
    "is_primary_access": 1, // integer — copied from the partner
    "is_secondary_access": 0, // integer — copied from the partner
    "is_preipo_access": 1 // integer — copied from the partner
  }
}
```

Failure (`status` `0`): message is the first validation error.

---

### POST `/api/v2/business/investor/kyc/cml/read`

Auth: partner API token.

`multipart/form-data` because `cml` is a file. The example lists the form fields in JSON comment style. Do not send `cml` as a JSON string.

Does not set `preipo_kyc_status`.

```
{
  "investor_id": 88, // integer required — owned investor; see ownership below
  "cml": null // file required — PDF; max 10000 KB (validation message: 10 MB)
}
```

Ownership (`Investor not found` when it fails). The investor is not deleted, and one of these is true:

- `is_self` = `0` and `partner_id` is this partner or one of this partner's relation managers (same non-self scope as the investor list when `relation_manager_ids` is omitted).
- `is_self` = `1` and `partner_id` is this partner.

Another partner's investor, including another partner's self investor, is rejected.

Success (`status` `1`): message `PDF parsed successfully.` `data`:

```
{
  "dp_id": "IN300000", // string or null from the PDF
  "client_id": "12345678", // string
  "pan_no": "ABCDE1234F", // string
  "account_holder_name": "PARTNER NAME", // string
  "account_number": "000123456789", // string
  "ifsc_code": "HDFC0000001", // string
  "bank_name": "HDFC BANK", // string or null
  "dob": "" // string — parser returns an empty string
}
```

A failed parse returns `status` `0`. The controller then stores the file, creates a pending manual demat row, and dispatches `SendPendingKycAdminNotification`. Message: `PDF could not be auto-read. Sent for manual verification.` Upload failure message: `File upload failed`. `preipo_kyc_status` stays unchanged.

---

### POST `/api/v2/business/investor/kyc/cml/save`

Auth: partner API token.

`multipart/form-data` when `cml_file` is sent. The example lists the form fields in JSON comment style.

`investor_id` uses the same ownership rule as read. The investor must belong to this partner under that rule.

On success, `DematKycService::saveDematKyc` sets that investor's `preipo_kyc_status` = `1` and sets the investor `name` from `name`.

```
{
  "investor_id": 88, // integer required — owned investor; same rule as cml/read
  "dp_id": "IN300000", // required — no extra type rule in validation
  "client_id": "12345678", // required
  "pan_no": "ABCDE1234F", // required
  "name": "Partner Name", // required — stored as investor name and PAN name
  "account_number": "000123456789", // optional — nullable; bank row is written only when this is present
  "ifsc_code": "HDFC0000001", // optional — nullable
  "bank_name": "HDFC BANK", // optional — nullable
  "dob": "31-01-1990", // optional — nullable; date; format d-m-Y
  "cml_file": null // file optional — PDF; max file_document_max_size MB
}
```

Success (`status` `1`): message `KYC details saved successfully.` No `data`.

Failure (`status` `0`): message `Failed to save KYC details.` and `error` when the service returns one. An investor outside the ownership rule returns `Investor not found` and does not change `preipo_kyc_status`.

---

## `self_investor_id` on existing login and profile

No new login or profile route was added. These existing V1 endpoints already return `self_investor_id` on the partner object (`PartnerRepository::attachSelfInvestorId`):

- `POST /api/v1/business/login` — `headtoken` only. Body: `mobile_no` (required, 10 digits), `password` (required), `firebase_token` (required), `device_id` (required string, max 255), `device` (required — `web` | `android` | `ios` | `desktop` | `macos`). Success message `Login Success`. `data` is the partner (password hidden) plus `token` and `self_investor_id`, with `country`, `city`, and `state`.
- `GET /api/v1/business/profile` — partner API token. Success message `Profile`. `data` is the same partner shape, including `self_investor_id`, without a new token.

`self_investor_id` is the `id` of the investor with `partner_id` = this partner and `is_self` = `1`. A Relation Manager gets `null` and uses the parent partner's investor. It is also `null` when that self investor row does not exist.
