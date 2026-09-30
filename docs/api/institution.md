# Institution partner company and deals API

Source: `routes/api.php` prefix `v2` → `business` → `institution`. Controller: `App\Http\Controllers\Api\V2\Business\CompanyController`. Persistence: `App\Repositories\V2\SellerCompanyRepository` (`checkDuplicate`, `createForInstitution` → `persistPendingCompany`, `sectors`, `listLite`, `listForInstitution`, `detailForInstitution`, `mySubmissionsForInstitution`, `savePromoters`, `saveShareholders`, `listInstitutionDeals`, `createInstitutionDeal`, `createInstitutionDealsBulk`, `updateInstitutionDeal`, `deleteInstitutionDeal`). Pricing: `App\Services\CompanyDealPricing`.

Company submit, catalog read, promoters, shareholders, and company deals are on this prefix. These routes do not write `seller_company_share_price`. A non-hot deal create upserts today's `company_share_price` for each affected company and dispatches `CalcuatePricingAutoJob` once. Hot deals stay on `company_deals` and are excluded from that history. Seller `POST /api/v2/seller/company/update-share-price` is not on this prefix: that write stores `seller_company_share_price.seller_id`, and an Institution partner id is never written into a seller id column. Deal list and create already live at `GET` and `POST` `.../company/deals` (the seller paths are `.../deals/list` and `.../deals/create`). `GET /api/v2/business/company/list` and `GET /api/v2/business/company/detail` stay the shared partner catalog. The Institution list and detail below are separate routes.

Both `unlisted` and `secondary` companies use the same paths. `type` selects which one on company submit. Deals use an approved company of either type.

## Auth

Every request sits under `ApiHeaderAuthMiddleware` and `auth:partner-api-guard`.

- Header `headtoken` — app header token.
- Header `Authorization: Bearer <token>` — Sanctum token for a `PartnerModel` (`partner-api-guard`).
- `partner.type` must be `Institution` (`PartnerTypeEnum::institution`). Any other partner type is rejected with `status` `0` and message `Only Institution partners can submit a company`. The same gate is used for every route in this prefix.

## Endpoints

| Method | Path | Purpose |
|--------|------|---------|
| POST | `/api/v2/business/institution/company/check-duplicate` | Check CIN and/or legal name before submit |
| POST | `/api/v2/business/institution/company` | Create a company that is approved and live for partners immediately |
| GET | `/api/v2/business/institution/company/sectors` | Active sectors for the create dropdown |
| GET | `/api/v2/business/institution/company/list` | Approved catalog. `is_editable` when this Institution submitted the company |
| GET | `/api/v2/business/institution/company/list-lite` | Light approved catalog, no pagination |
| GET | `/api/v2/business/institution/company/detail` | Approved catalog detail, or this Institution's own non-rejected submission |
| GET | `/api/v2/business/institution/company/my-submissions` | This Institution's submissions |
| POST | `/api/v2/business/institution/company/promoters` | Replace promoters on a company this Institution submitted |
| POST | `/api/v2/business/institution/company/shareholders` | Replace shareholders on a company this Institution submitted |
| POST | `/api/v2/business/institution/company/deals` | Create a deal on an approved unlisted or secondary company |
| POST | `/api/v2/business/institution/company/deals/bulk` | Insert sell and/or buy deals from price rows |
| GET | `/api/v2/business/institution/company/deals` | List this Institution's deals only |
| POST | `/api/v2/business/institution/company/deals/update` | Update one of this Institution's deals |
| POST | `/api/v2/business/institution/company/deals/delete` | Soft-delete one of this Institution's deals |

---

### POST `/api/v2/business/institution/company/check-duplicate`

Auth: partner API token; `partner.type` must be `Institution`.

JSON body. At least one of `cin` or `company_name` is required (enforced after validation). If both are empty the message is `Provide cin and/or company_name (legal name) to check`.

```
{
  "cin": "U12345MH2020PTC123456", // string optional — max 255; provide cin and/or company_name
  "company_name": "Example Private Limited", // string optional — max 255; legal name
  "type": "unlisted" // string optional — unlisted | secondary; default unlisted
}
```

Duplicate rules (non-deleted companies only; spaces removed and compared case-insensitively):

- `cin` matches any company, regardless of `type`.
- `company_name` matches the legal name on a company of the same `type` (`unlisted` or `secondary`). When `type` is omitted, the name check uses `unlisted`.

Success (`status` `1`):

```
{
  "status": 1,
  "message": "No duplicate company found", // or "Duplicate company found"
  "data": {
    "is_duplicate": false, // boolean
    "matches": [] // when duplicate: { "match_on": "cin" | "company_name" | "cin,company_name", "company": { id, uuid, slug, brand_name, company_name, cin, type, approval_status } }
  }
}
```

---

### POST `/api/v2/business/institution/company`

Auth: partner API token; `partner.type` must be `Institution`.

`multipart/form-data` because `logo` is a file. The example below lists the form fields in JSON comment style. Do not send `logo` as a JSON string.

Same field rules as seller company create (`persistPendingCompany`). `type` is required and must be `unlisted` or `secondary`.

```
{
  "type": "unlisted", // string required — unlisted | secondary
  "cin": "U12345MH2020PTC123456", // string required — max 255
  "brand_name": "Example", // string required — max 255
  "company_name": "Example Private Limited", // string required — max 255; legal name used in the duplicate check
  "sector": 3, // integer required — master_sectors.id that exists and is not deleted
  "about": "Company description", // string required
  "min_investment_amount": 10000, // numeric required — min 0
  "lot_size": "10", // string required — max 255
  "market_cap": 150.5, // numeric required — min 0
  "pe_ratio": 22.4, // numeric required
  "pb_ratio": 3.1, // numeric required
  "debt_to_equity": 0.4, // numeric required — min 0
  "roe": 18.2, // numeric required
  "book_value": 120, // numeric required — min 0
  "face_value": 10, // numeric required — min 0
  "logo": null, // file optional — image; mimes from setting file_image_extensions_allowed; max file_image_max_size MB
  "alternative_names": "example, example ltd", // string optional — comma list, stored lowercased and trimmed
  "list_order": "10", // string optional — max 255
  "depository": "NSDL", // string optional — max 255
  "pan_number": "ABCDE1234F", // string optional — size 10
  "isin_number": "INE000A01012", // string optional — size 12
  "rta": "Link Intime", // string optional — max 255
  "total_shares": 1000000 // numeric optional — min 0
}
```

The server saves the row. These are not request fields:

- `approval_status` = `approved`. Admin approval is not required. Partner company list shows the row when `approval_status` is `approved`, `status` is `0`, and `is_deleted` is `0`. Company detail shows it when `approval_status` is `approved`.
- `submitted_by_partner_id` = the authenticated Institution partner id.
- `submitted_by_seller_id` stays null.
- `status` = `0`, `is_deleted` = `0`, `category` = null, `min_investment_type` = `quantity`, `is_free_processing_fee` = `1`.
- `processing_fee_percentage` is not a request field. A posted value is ignored. The server stores the admin processing fee (`app_settings` key `processing_fee_percentage`). Admin-set. Default 1. Min 1. Max 100.
- `commission` is not a request field. A posted `commission` or `commission_percentage` is ignored. `company.commission` is set from that same admin processing fee on create. There is no Institution company update route, so a later edit does not take a new commission.
- Fundamentals `fifty_two_week_high` and `fifty_two_week_low` are stored as `0`. `cin_number` is copied from `cin`.

Create runs the same duplicate check as check-duplicate. A match returns `status` `0`, message `Company already exists (...)`, and `data` with `is_duplicate` and `matches`.

Success (`status` `1`): message `Company created successfully. It is live.` and `data` is the company with `sector` (`id`, `name`) and `fundamentals`.

---

### GET `/api/v2/business/institution/company/sectors`

Auth: partner API token; `partner.type` must be `Institution`.

No body. No query parameters.

Success (`status` `1`): message `Sector list`. `data` is active, non-deleted sectors ordered by `name`: `id`, `name`, `icon_image`, `url_slug`. Use `id` as `sector` on company create.

---

### GET `/api/v2/business/institution/company/list`

Auth: partner API token; `partner.type` must be `Institution`.

Approved catalog only (`approval_status` = `approved`, `status` = `0`, `is_deleted` = `0`). This is not `GET /api/v2/business/company/list`.

Query parameters (all optional):

```
{
  "type": "unlisted", // string optional — unlisted | secondary; default unlisted
  "category": "All", // string optional — All or a PreIpoCategoryEnum value; default excludes Listed
  "sector": "technology", // string optional — master_sectors.url_slug
  "search": "example", // string optional — brand_name, company_name, or keywords
  "skip": 0, // integer optional — min 0
  "take": 15 // integer optional — min 1; default app pagination limit
}
```

Each company includes `is_price_updated_today`, admin `price_updated_today`, and `is_editable`. `is_editable` is `true` when `submitted_by_partner_id` is this Institution. `submitted_by_seller_id` and `submitted_by_partner_id` are not returned.

Success (`status` `1`): message `Company list`. Also returns `total`, `skip`, and `take`.

---

### GET `/api/v2/business/institution/company/list-lite`

Auth: partner API token; `partner.type` must be `Institution`.

Approved catalog, no pagination. Fields: `id`, `uuid`, `slug`, `brand_name`, `company_name`, `logo`.

```
{
  "type": "All", // string optional — All | unlisted | secondary; default All
  "search": "example" // string optional — brand_name or company_name
}
```

Success (`status` `1`): message `Company list lite`. `data` is the array, ordered by `brand_name`.

---

### GET `/api/v2/business/institution/company/detail`

Auth: partner API token; `partner.type` must be `Institution`.

One of `slug`, `id`, or `uuid` is required. This is not `GET /api/v2/business/company/detail`.

Returns an approved company, or this Institution's own submission when `submitted_by_partner_id` is this partner and `approval_status` is not `rejected`. Any other company returns `status` `0` and message `Company not found`.

```
{
  "slug": "example", // string — required when id and uuid are omitted
  "id": 12, // integer — required when slug and uuid are omitted
  "uuid": "…" // string uuid — required when slug and id are omitted
}
```

Success (`status` `1`): message `Company detail`. `data` includes `promoters`, `share_holders` (year groups of `name` + `percentage`), and `seller_share_prices` (today's seller quotes, `sell_price` ascending, nested `seller`). Reading those seller quotes does not write a partner id into a seller id column.

---

### GET `/api/v2/business/institution/company/my-submissions`

Auth: partner API token; `partner.type` must be `Institution`.

Companies where `submitted_by_partner_id` is this partner and `is_deleted` is `0`. Pending, approved, and rejected rows are included unless filtered.

```
{
  "type": "unlisted", // string optional — unlisted | secondary
  "approval_status": "approved", // string optional — pending | approved | rejected
  "skip": 0, // integer optional — min 0
  "take": 15 // integer optional — min 1; default app pagination limit
}
```

Success (`status` `1`): message `My company submissions`. `data` includes `sector` and `fundamentals`. Also returns `total`, `skip`, and `take`.

---

### POST `/api/v2/business/institution/company/promoters`

Auth: partner API token; `partner.type` must be `Institution`.

JSON body. Replace-all promoters for one company this Institution submitted (`submitted_by_partner_id` = this partner, not deleted, `approval_status` not `rejected`). Any other company returns `status` `0` and message `Company not found`. Omit `promoters` or send `[]` to clear them. Read via detail.

```
{
  "company_id": 12, // integer required
  "promoters": [ // array optional — omit or [] clears all
    {
      "name": "Ada Example", // string required — max 255
      "designation": "Director", // string required — max 255; stored title case
      "experience": "10 Years", // string required — max 255; stored title case
      "url": "https://example.com" // string optional — max 2000
    }
  ]
}
```

Success (`status` `1`): message `Promoters updated`. No `data`.

---

### POST `/api/v2/business/institution/company/shareholders`

Auth: partner API token; `partner.type` must be `Institution`.

JSON body. Replace-all shareholders for one company this Institution submitted. Same ownership rule as promoters. The body is year-grouped, matching detail `share_holders`. The same person across years is stored as one holder. Omit `shareholders` or send `[]` to clear them.

```
{
  "company_id": 12, // integer required — company this Institution submitted
  "shareholders": [ // array optional — omit or [] clears all
    {
      "year": "2025", // string required — max 20
      "shareholders": [ // array optional
        { "name": "Shareholder 1", "percentage": 40.5 }, // name required max 255; percentage 0–100
        { "name": "Shareholder 2", "percentage": 25 }
      ]
    }
  ]
}
```

Success (`status` `1`): message `Shareholders updated`. No `data`.

---

### POST `/api/v2/business/institution/company/deals`

Auth: partner API token; `partner.type` must be `Institution`.

JSON body. An approved company may have more than one deal. `company_id` must be an approved, non-deleted `unlisted` or `secondary` company. A company that fails that rule returns `status` `0` and message `Company not found`.

The request field `share_price` is the base amount the client typed. It is stored as `company_deals.base_price`. The deal's `share_price` is that base plus the admin processing fee: `round(base_price * (1 + processing_fee_percentage / 100), 2)`. Example: base 100 and fee 1% stores `share_price` 101. The client does not set the fee-inclusive price. This endpoint does not write `seller_company_share_price`.

```
{
  "company_id": 12, // integer required — approved unlisted or secondary
  "deal_type": "sell", // string required — buy | sell
  "available_quantity": 1000, // integer required — min 0
  "share_price": 250.50, // numeric required — min 0; base amount. Stored as base_price. Saved share_price adds the admin processing fee
  "minimum_qty": 10, // integer required — min 1
  "status": "available", // string optional — available | half_sold | sold — default available
  "is_hot_deal": false, // boolean optional — default false; saved on company_deals.is_hot_deal. true excludes this deal from share-price history
  "expired_at": "2026-12-31 23:59:59" // datetime optional — Y-m-d H:i:s — null or omitted = no expiry
}
```

The server saves the row. These are not request fields:

- `base_price` = the request `share_price`.
- `share_price` on the row = `base_price` plus the admin processing fee.
- `created_by_partner_id` = the authenticated Institution partner id.
- `created_by_seller_id` stays null. Seller ids are not reused.
- `processing_fee_percentage` is not a request field. A posted value is ignored. The server stamps the admin processing fee (`app_settings` key `processing_fee_percentage`). Admin-set. Default 1. Min 1. Max 100. The same percent is used in the share-price formula.

When `is_hot_deal` is false, today's `company_share_price` row for that company is upserted from non-deleted, not-expired, `is_hot_deal` = false deals, then `CalcuatePricingAutoJob` is dispatched once. Hot deals are stored and do not change history, and a hot-only create does not dispatch the job.

History values for that company:

- `price` = minimum fee-inclusive `share_price` of the non-hot sell deals. If there is no sell deal, the buy `share_price` is used so the row is valid.
- `distributer_price` = minimum fee-inclusive `share_price` of the non-hot buy deals. If there is no buy deal, the sell `share_price` is used.
- `base_price` = minimum `base_price` of those non-hot sell deals, otherwise the non-hot buy deals.
- One row per company per date. If today's row already exists, it is updated.

Success (`status` `1`): message `Deal created`. `data` is the deal:

```
{
  "uuid": "…", // string
  "deal_type": "sell", // buy | sell
  "available_quantity": 1000, // integer
  "base_price": 100, // number — amount typed by the client (request share_price)
  "share_price": 101, // number — base_price plus the admin processing fee (example fee is 1%)
  "minimum_qty": 10, // integer
  "processing_fee_percentage": 1, // number — admin processing fee stamped on create (default 1, min 1, max 100)
  "status": "available", // available | half_sold | sold
  "is_hot_deal": false, // boolean
  "expired_at": "2026-12-31 23:59:59", // datetime or null
  "is_expired": false, // boolean
  "is_mine": true, // boolean — true when created_by_partner_id is this partner
  "company": {
    "id": 12, // integer
    "brand_name": "Example", // string
    "slug": "example", // string
    "logo": null, // string or null
    "type": "unlisted" // unlisted | secondary
  }
}
```

---

### POST `/api/v2/business/institution/company/deals/bulk`

Auth: partner API token; `partner.type` must be `Institution`. Other partner types are rejected with the same gate as the other institution company routes.

JSON body. `sell` and `buy` are arrays. Either may be empty or omitted. At least one valid row is required, otherwise `status` `0` and message `At least one valid sell or buy row is required.`

A sell row is inserted only when `company_id`, `sell_price`, and `min_qty` are present, `sell_price` > 0, and `min_qty` >= 1. A buy row uses `buy_price` the same way. `total_qty` is optional. Blank rows and rows with a missing or zero price are skipped. A skipped row does not fail the request.

Each valid sell row inserts one `company_deals` row with `deal_type` = `sell`. Each valid buy row inserts one with `deal_type` = `buy`. Two companies are two deals. The same company in both lists is two deals. Rows are always inserted. An older deal for that company is not updated.

`sell_price` or `buy_price` is the base amount. It is stored as `base_price`. `share_price` is `round(base_price * (1 + processing_fee_percentage / 100), 2)`.

`minimum_qty` = `min_qty`. `available_quantity` = `total_qty` when `total_qty` is sent and `>= min_qty`, otherwise `0`. `status` = `available`. `is_hot_deal` = false. `expired_at` = null. `created_by_partner_id` = this Institution. `created_by_seller_id` = null. `processing_fee_percentage` = the admin processing fee.

`company_id` must be an approved, non-deleted `unlisted` or `secondary` company. A non-blank row that fails that rule returns `status` `0` and a company-not-found message for that row (for example `Sell row 1 company not found (company_id 99).`). A non-blank row with a price but a missing company or `min_qty` < 1 is the same: the error is returned and no deals are created. Inserts run in one transaction.

After a successful insert, today's `company_share_price` is upserted for each affected company using the non-hot history rule above, then `CalcuatePricingAutoJob` is dispatched once for the request. This endpoint does not create hot deals and does not write `seller_company_share_price`.

```
{
  "sell": [ // array optional — omit or [] is allowed
    {
      "company_id": 12, // integer — required on a valid row; approved unlisted or secondary
      "sell_price": 20, // numeric — required on a valid row; > 0; stored as base_price
      "min_qty": 1000, // integer — required on a valid row; >= 1; stored as minimum_qty
      "total_qty": null // integer optional — available_quantity when sent and >= min_qty, otherwise 0
    }
  ],
  "buy": [ // array optional — omit or [] is allowed
    {
      "company_id": 12, // integer — required on a valid row
      "buy_price": 18, // numeric — required on a valid row; > 0; stored as base_price
      "min_qty": 500, // integer — required on a valid row; >= 1
      "total_qty": null // integer optional
    }
  ]
}
```

Success (`status` `1`): message `Deals created`. `data` is an array of the same deal object as single create (`base_price` is the typed amount, `share_price` includes the fee).

---

### GET `/api/v2/business/institution/company/deals`

Auth: partner API token; `partner.type` must be `Institution`.

Returns only deals where `created_by_partner_id` is this partner and the company is still an approved, non-deleted `unlisted` or `secondary` company. Seller-created deals are not included.

Query parameters (all optional):

```
{
  "company_id": 12, // integer optional — approved unlisted or secondary; unknown company returns "Company not found"
  "type": "All", // string optional — All | unlisted | secondary; default All
  "skip": 0, // integer optional — min 0
  "take": 15 // integer optional — min 1; default app pagination limit
}
```

Success (`status` `1`): message `Deal list`. `data` is an array of the same deal object as create. Also returns `total`, `skip`, and `take`.

---

### POST `/api/v2/business/institution/company/deals/update`

Auth: partner API token; `partner.type` must be `Institution`.

JSON body. Updates one deal owned by this Institution (`created_by_partner_id` = this partner) whose company is still approved `unlisted` or `secondary`. Any other deal returns `status` `0` and message `Deal not found`.

`company_id` and `processing_fee_percentage` cannot be changed. The sent `share_price` is stored as the deal `share_price` and does not add the fee again. `base_price` is left as it was. This request does not write `seller_company_share_price` or `company_share_price`.

```
{
  "uuid": "…", // string required — deal uuid
  "deal_type": "sell", // string required — buy | sell
  "available_quantity": 1000, // integer optional — min 0; omit to keep the current quantity
  "share_price": 250.50, // numeric required — min 0; deal price only
  "minimum_qty": 10, // integer required — min 1
  "status": "available", // string required — available | half_sold | sold
  "is_hot_deal": false, // boolean optional — default false; saved on company_deals.is_hot_deal
  "expired_at": "2026-12-31 23:59:59" // datetime optional — Y-m-d H:i:s — omit or null clears expiry
}
```

Success (`status` `1`): message `Deal updated` and `data` is the same deal object as create.

---

### POST `/api/v2/business/institution/company/deals/delete`

Auth: partner API token; `partner.type` must be `Institution`.

JSON body. Soft-deletes (`is_deleted` = true) one deal owned by this Institution whose company is still approved `unlisted` or `secondary`. Any other deal returns `status` `0` and message `Deal not found`.

```
{
  "uuid": "…" // string required — deal uuid
}
```

Success (`status` `1`): message `Deal deleted`. No `data`.

Business company detail (`GET /api/v2/business/company/detail`) includes these deals with the other non-expired deals for that company. When `created_by_partner_id` is set, the deal has nested `partner` (`id`, `uuid`, `name`, `profile_photo`). Seller-created deals still nest `seller` and set `partner` to null. Hot-deal cards do not attach a creator.
