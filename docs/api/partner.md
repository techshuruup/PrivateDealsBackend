# Partner business API

Source: `routes/api.php` prefix `v2` → `business`. Investor list and create: `App\Http\Controllers\Api\V2\Business\CommonController` (`investorList`, `investorCreate`). Pre-IPO buy: same controller (`preIpoBuy`). CML: `App\Http\Controllers\Api\V2\Business\KycController` (`readCml`, `saveCml`). Save persistence: `App\Services\DematKycService` (`saveDematKyc`). PDF parse: `App\Services\DematPdfParsingService` (`processPdf`). `self_investor_id` is attached in `App\Repositories\PartnerRepository` (`attachSelfInvestorId`) on the existing V1 login and profile methods.

Institution company submit and company deals are not on this page. Those routes are in [institution.md](institution.md).

## Auth

Investor list, investor create, CML, Pre-IPO buy, and the buying-partner order-step routes sit under `ApiHeaderAuthMiddleware` and `auth:partner-api-guard`.

- Header `headtoken` — app header token.
- Header `Authorization: Bearer <token>` — Sanctum token for a `PartnerModel` (`partner-api-guard`).

Any authenticated partner type can call these routes. They are not limited to `Institution`.

## Endpoints

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/api/v2/business/company/detail` | Company detail. `deals` is normal (default) or hot |
| GET | `/api/v2/business/investor` | Investor list; this partner's self investor is first |
| POST | `/api/v2/business/investor` | Create an investor for the logged-in partner |
| POST | `/api/v2/business/investor/kyc/cml/read` | Parse a CML PDF for one owned investor |
| POST | `/api/v2/business/investor/kyc/cml/save` | Save demat KYC and set `preipo_kyc_status` = `1` |
| POST | `/api/v2/business/pre-ipo/buy` | Place one or more Pre-IPO buy orders on Institution sell deals |
| GET | `/api/v2/business/pre-ipo/transaction-list` | This partner's Pre-IPO orders. An `order_step` row is the new payload only |
| GET | `/api/v2/business/pre-ipo/transaction/detail` | One order this partner can act for |
| POST | `/api/v2/business/pre-ipo/transaction/cancel` | Cancel a `mandate_pending` order |
| POST | `/api/v2/business/pre-ipo/transaction/payment-receipt` | Upload a payment receipt on `payment_pending` |
| POST | `/api/v2/business/pre-ipo/transaction/confirm-share-transfer` | Complete a `share_transfer_confirmation_pending` order |

---

### GET `/api/v2/business/company/detail`

Auth: partner API token. Any partner type.

Query `slug` is required. Query `deal_type` is optional: `normal` (default) or `hot`. This filter chooses the `deals` list. Each deal row's `deal_type` is still `buy` or `sell`.

`normal`: not deleted, `is_hot_deal` = false, `created_at` today, `status` `available`.

`hot`: not deleted, `is_hot_deal` = true, not expired (`expired_at` null or `expired_at` > now), `status` `available`.

Both lists include buy and sell rows. A row nests `seller` when `created_by_seller_id` is set, and `partner` when `created_by_partner_id` is set. The other creator is null.

Success (`status` `1`): message `Detail`. `data.deals` is the filtered list.

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

### POST `/api/v2/business/pre-ipo/buy`

Auth: partner API token. `Api\V2\Business\CommonController::preIpoBuy`.

Places one or more Pre-IPO buy orders. Every item is checked before any insert. One failure returns `status` `0` and inserts nothing. Success is one database transaction, one `pre_ipo_transaction` row per item.

This does not change V1 buy or V2 investor `POST /api/v2/investor/pre-ipo/buy`.

```
{
  "orders": [ // array required — minimum 1
    {
      "deal_id": 1, // integer required — see deal rules below
      "investor_id": 2, // integer required — owned investor; same ownership as cml/read
      "shares": 10, // integer required — minimum 1, and at least the deal minimum_qty
      "share_price": 102 // numeric required — price this partner quotes the investor
    }
  ]
}
```

Deal (`deal_id`). The deal is not deleted, not expired, `status` is `available`, `deal_type` is `sell`, and `created_by_partner_id` is set. A null `created_by_partner_id` is rejected. There is no seller fallback. `company_id` on the order is the deal's `company_id`.

Investor (`investor_id`). Same ownership as CML read: not deleted, and either a non-self investor of this partner or one of their relation managers, or this partner's self investor. Another partner's investor is rejected.

Shares. When `available_quantity` is greater than 0, shares must not exceed it. Repeated `deal_id` values in the same request are added together and must still fit. `available_quantity` is not decremented.

`share_price` has no minimum against the deal price.

Not accepted from the client (ignored if sent): `distributer_price`, `payment_mode`, `is_distributer`, `seller_id`, `partner_id`. No coupons.

Saved on each row (`status` stays `0`, `order_step` is `mandate_pending`):

| Column | Value |
|--------|--------|
| `investor_id` | Client on the item |
| `company_id` | Deal `company_id` |
| `deal_id` | Deal id |
| `partner_id` | Deal `created_by_partner_id` (the Institution), not the logged-in partner |
| `seller_investor_id` | Institution self investor (`investor.partner_id` = that Institution, `is_self` = `1`, not deleted). A missing self investor rejects the item |
| `seller_id` | null |
| `base_price` | Deal `base_price` (example 99, what the Institution entered). A null `base_price` rejects the item |
| `distributer_price` | Deal `share_price` (example 100, base plus the admin processing fee) |
| `share_price` | Item `share_price` (example 102) |
| `investment_amount`, `payable_amount` | `shares * share_price` |
| `is_distributer` | true |
| `payment_mode` | `RTGS` |
| `instrument` | `equity` |
| `order_step` | `mandate_pending` |

The partner keeps `share_price - distributer_price` per share (102 − 100 = 2). The Private Deals fee is inside `distributer_price` (100 − 99).

Success (`status` `1`): message `Orders placed successfully.` when every buy mandate was sent to Digio. If a mandate send fails, the orders are still saved and the message is `Orders placed. The buy mandate could not be sent.` Each item in `data` is the order-step payload below, plus `mandate_sent`. Integer `status` stays `0` and is not in that payload. Printed mandate expiry is 7 days after `created_at`. See [pre-ipo-order-steps.md](../workflows/pre-ipo-order-steps.md).

Failure (`status` `0`): the first failed item's message. Nothing is inserted.

---

### GET `/api/v2/business/pre-ipo/transaction-list`

Auth: partner API token. `Api\V2\Business\CommonController::preIpoTransactionList`.

Query: `skip` (optional integer, default 0), `take` (optional integer, default app pagination limit).

Orders whose `investor_id` is in the same set as `GET /api/v2/business/investor` (this partner, their relation managers' non-self investors, and this partner's self investor).

Old rows (`order_step` null) keep `status_list` from `PreIpoTransactionHelper::getStatusListForApplicationV2` and omit `order_step`.

A row with `order_step` set does not include `status_list`, `current_status`, `percentage`, `is_processing`, `deal_slip`, `approval_file`, `rejection_file`, integer `status`, or `seller_master` bank fields. `current_step` and `next_step` are the buying-partner labels from `PreIpoOrderStepHelper::labels`. They are not the old `PreIpoModel` status `0`–`5` strings.

Each new row has these keys:

`id`, `transaction_invoice_no`, `order_step`, `current_step`, `next_step`, `action` (array of action names, or null), `sign_link` (mandate link on `mandate_pending`, deal-slip link on `deal_slip_pending`, and only when this partner's self investor is the signer and that link exists; otherwise null), `investor` (`id`, `name`), `company` (`id`, `brand_name`, `logo`), `deal_id`, `shares`, `base_price`, `distributer_price`, `share_price`, `investment_amount`, `payable_amount`, `cancellation_reason` (set when `order_step` is `cancelled`, otherwise null), `payment_details` (from `payment_pending` onward: `amount` and CML `account` with `account_holder_name`, `bank_name`, `account_number`, `ifsc_code`; `account` null when that bank row is missing; the whole value is null before `payment_pending`), `payment_receipt` and `share_transfer_receipt` (file `id`, `name`, `path`, `url`, or null), `created_at`.

The bank on `payment_details.account` is the Institution self investor's CML `user_bank_accounts` row (`seller_investor_id`). It is not `seller_master`.

Success (`status` `1`): message `Transaction List`. `data` is the page of orders. A page can mix old rows and new rows.

---

### GET `/api/v2/business/pre-ipo/transaction/detail`

Auth: partner API token. `Api\V2\Business\PreIpoOrderController::detail`.

Query: `transaction_id` (integer, required). The order's investor must be in the same scope as `GET /api/v2/business/investor`, and `order_step` must be set.

Success (`status` `1`): message `Transaction detail`. `data` is the same new-row object as the list (keys above). Cancel, payment-receipt, and confirm-share-transfer return that same object.

Failure (`status` `0`): `Transaction not found`, or the validation message.

---

### POST `/api/v2/business/pre-ipo/transaction/cancel`

Auth: partner API token. `Api\V2\Business\PreIpoOrderController::cancel`.

Only `order_step` `mandate_pending`. Integer `status` stays `0`.

```
{
  "transaction_id": 1, // integer required
  "reason": "Investor changed their mind" // string required, max 1000
}
```

Success (`status` `1`): message `Transaction cancelled.` `data` is the order. `order_step` is `cancelled` and `cancellation_reason` is the reason.

Failure (`status` `0`): `Transaction not found`, `This action is not available for the current order step.`, or the validation message.

---

### POST `/api/v2/business/pre-ipo/transaction/payment-receipt`

Auth: partner API token. `Api\V2\Business\PreIpoOrderController::paymentReceipt`. Multipart.

Only `order_step` `payment_pending`. Stores a `Payment Receipt` document and a `pre_ipo_transaction_payments` row, then sets `order_step` to `payment_confirmation_pending`. Integer `status` stays `0`.

```
{
  "transaction_id": 1, // integer required
  "file": null // file required — png, jpg, jpeg, or pdf; max file_document_max_size MB
}
```

Success (`status` `1`): message `Payment receipt uploaded.` `data` is the order, including `payment_receipt`.

Failure (`status` `0`): `Transaction not found`, `This action is not available for the current order step.`, `Payment receipt upload failed.`, or the validation message.

---

### POST `/api/v2/business/pre-ipo/transaction/confirm-share-transfer`

Auth: partner API token. `Api\V2\Business\PreIpoOrderController::confirmShareTransfer`.

Only `order_step` `share_transfer_confirmation_pending`. Sets `order_step` to `completed` and writes `portfolio_preipo` through `UtillsHelper::preIpoPortfolio`. `portfolio_id` is that row. Integer `status` stays `0`.

```
{
  "transaction_id": 1 // integer required
}
```

Success (`status` `1`): message `Transaction completed.` `data` is the order.

Failure (`status` `0`): `Transaction not found`, `This action is not available for the current order step.`, or the validation message.

---

## `self_investor_id` on existing login and profile

No new login or profile route was added. These existing V1 endpoints already return `self_investor_id` on the partner object (`PartnerRepository::attachSelfInvestorId`):

- `POST /api/v1/business/login` — `headtoken` only. Body: `mobile_no` (required, 10 digits), `password` (required), `firebase_token` (required), `device_id` (required string, max 255), `device` (required — `web` | `android` | `ios` | `desktop` | `macos`). Success message `Login Success`. `data` is the partner (password hidden) plus `token` and `self_investor_id`, with `country`, `city`, and `state`.
- `GET /api/v1/business/profile` — partner API token. Success message `Profile`. `data` is the same partner shape, including `self_investor_id`, without a new token.

`self_investor_id` is the `id` of the investor with `partner_id` = this partner and `is_self` = `1`. A Relation Manager gets `null` and uses the parent partner's investor. It is also `null` when that self investor row does not exist.
