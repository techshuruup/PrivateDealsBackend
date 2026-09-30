## Pre-IPO (v2 business + investor) — Unlisted/Secondary API changes

Last updated: 25 Aug 2026

### Goal
Make sure “unlisted shares” vs “secondary shares” don’t mix in the same API payloads, by introducing a market field (`company.type`) and applying it to:
- Pre-IPO home (business v2)
- Company list (business v2)
- Pre-IPO news + sectors (business v2)
- Pre-IPO news list (business v2 “common”)
- Pre-IPO transaction list (business v2)

This doc also captures pagination / payload shape changes made during this work.

---

## 1) Data model changes

### New DB column: `company.type`
- Enum: `unlisted` | `secondary`
- Default: `unlisted`
- Migration: `database/migrations/2026_08_20_173700_add_type_column_to_company_table.php`
- Enum file: `app/Enums/CompanyTypeEnum.php`

### `company` slug
- Pre-IPO home and company list responses now include `company.slug` (used by `companyDetail`).
- Auto-generation: admin `CompanyController` sets `slug` on create/update/import via `AdminHelper::companySlug` (from brand name). Edit regenerates from current brand name (same as sector). Backfill: `php artisan seo:generate-slugs`.

---

## 2) Business v2 — Pre-IPO home APIs

### 2.1 `GET /api/v2/business/home/pre-ipo`

Payload behavior changes:
1. Each key list is capped to **max 8 items**:
   - `all`
   - `exclusive_deals`
   - `liquid_stocks`
   - `drhp`
   - `trending`
2. `all` now returns **full pre-IPO formatted company objects** (same shape as trending/exclusive previously did).
3. `support` section was removed from this endpoint.
4. Each company object includes:
   - `slug` (not `url_slug`)
   - `sector` as the sector **name string only** (or `null` if unset) — not the full sector object
   - and the same formatted fields used in trending/exclusive (prices history formatting, grab opportunity, min investment, etc.)
5. `top_gainers` / `top_losers`:
   - `CompanyDailySharePriceModel::getTopGainersLosers` response rows now include `slug` as well.

Example (no extra params):
- `/api/v2/business/home/pre-ipo`

Market filtering:
- Hardcoded internally to **unlisted** (`company.type=unlisted`). Secondary companies are not included.

---

### 2.2 `GET /api/v2/business/home/pre-ipo/news-sectors`

Changes:
1. `news` list is limited to **10 items** (preview).
2. `news` + `sectors` are now based on **unlisted** companies only.
3. No need to pass any `type` param; it’s fixed to `unlisted` internally.
4. Each sector includes:
   - `company_count` — number of unlisted (Pre-IPO) companies in that sector
   - `companies` — list of `{ brand_name, logo }` for those companies (same field names as other company objects)

Example:
- `/api/v2/business/home/pre-ipo/news-sectors`

---

### 2.3 `GET /api/v2/business/home/secondary`

Combined secondary home under the `home/secondary` route group (same pattern as `home/pre-ipo`, so extra endpoints can be added later):

1. **One** company list only — key `all` (same shape as pre-ipo home company cards, including `sector` as name string only), capped to **max 8**.
2. No exclusive/liquid/drhp/trending/gainers/losers buckets.
3. Includes `news` (limit **10**) and `sectors` in the same response. Each sector includes `company_count` and a `companies` list of `{ brand_name, logo }` (secondary companies only).
4. Hardcoded to `company.type=secondary` (no `type` query param).

Response shape:
```json
{
  "data": {
    "all": [ /* formatted secondary companies */ ],
    "news": [ /* company news for secondary companies */ ],
    "sectors": [ /* id, name, url_slug, company_count, companies[{ brand_name, logo }] */ ]
  }
}
```

Example:
- `/api/v2/business/home/secondary`

---

## 3) Business v2 — Pre-IPO news list API (skip/take pagination)

### `GET /api/v2/business/common/preipo-news`

What it returns:
- `data`: array of `company_news` rows.

Pagination:
- Uses `skip` / `take` (not `page` / `per_page`).

Supported params:
- `skip` (default `0`)
- `take` (default `app_pagination_limit`, fallback `15`)
- `company_id` (optional)

Market filtering:
- Hardcoded internally to **unlisted** only.
- No `type` query parameter.

Examples:
- `/api/v2/business/common/preipo-news?skip=0&take=15`
- `/api/v2/business/common/preipo-news?company_id=123&skip=0&take=15`

---

## 4) Business v2 — Company list API (filters + skip/take)

### `GET /api/v2/business/company/list`

Pagination:
- Uses `skip` / `take`

Supported params:
- `type`:
  - allowed: `unlisted`, `secondary`
  - default (if omitted): `unlisted`
- `category` (string values; accepts enum values + `All`)
  - allowed:
    - `All`
    - `Trending`
    - `Coming Soon`
    - `Exclusive Deals`
    - `Listed`
    - `Liquid Stocks`
    - `DRHP`
  - default (`All` or omitted): **excludes** `category=Listed` companies (includes `category` null)
  - `category=Listed`: returns **only** listed companies
  - `category=DRHP`: `is_drhp=1`, excludes `Coming Soon` and `Listed`; **includes** companies with `category` null (SQL `!=` alone would drop nulls)
- `sector` (string; NOT `sector_id`)
  - expects `MasterSectorsModel.url_slug`
  - use the `url_slug` returned in `news-sectors` response
- `search` (brand name partial match)
- `skip` (default `0`)
- `take` (default `app_pagination_limit`, fallback `15`)

Response changes:
- Each company includes `slug` in addition to existing fields.

Examples:
- Unlisted market default:
  - `/api/v2/business/company/list?skip=0&take=15`
- Category + sector slug:
  - `/api/v2/business/company/list?type=unlisted&category=Trending&sector=fintech&skip=0&take=15`
- Secondary market:
  - `/api/v2/business/company/list?type=secondary&category=All&skip=0&take=15`

---

## 5) Business v2 — Pre-IPO transaction list (skip/take)

### `GET /api/v2/business/pre-ipo/transaction-list`

Pagination:
- Uses `skip` / `take`
- Response includes only:
  - `data` array of transactions
- No `pagination` meta object is returned.

Supported params:
- `skip` (default `0`)
- `take` (default `app_pagination_limit`, fallback `15`)

---

## 6) Routes grouping changes (for dev reference)

Business v2 route grouping was adjusted so news endpoints align under shared “common”/“home” paths:
- `home/pre-ipo` and `home/secondary` are both prefix groups (empty GET for home)
- `home/pre-ipo/news-sectors` remains preview (limit 10)
- full news list moved under:
  - `/api/v2/business/common/preipo-news`

---

## 7) Implementation notes (important gotchas)

1. Some “home” sublists share the same company objects; response formatting functions may mutate models.
   - During this work, a cloning approach was used in `getPreipoHome()` formatting to avoid cross-list mutation side effects.

2. `top_gainers` / `top_losers` are computed by `CompanyDailySharePriceModel::getTopGainersLosers`.
   - That method now includes `slug` per company row.

---

## Pending decision points (if/when secondary is added beyond filters)

Secondary discovery home is implemented as a **separate combined route**:
- `GET /api/v2/business/home/secondary` → `all` + `news` + `sectors` (type fixed to `secondary`)

Still open if needed later:
- Dedicated `common/secondary-news` (full paginated news list) like `common/preipo-news`
- Investor v2 parity for secondary home

---

## 8) Company deals on company detail

Admin manages deals at `/admin/company-deals` (Company sidebar → **Company Deals**). Table: `company_deals` (`CompanyDealModel`).

Business + Investor V2 `company/detail` eager-load non-deleted deals as `deals`:

```json
"deals": [
  {
    "uuid": "...",
    "available_quantity": 1000,
    "share_price": 125.5,
    "minimum_qty": 10,
    "processing_fee_percentage": 1,
    "status": "available"
  }
]
```

Status values: `available` | `half_sold` | `sold` (manual in admin). Empty array when the company has no deals. Purchase flow does not yet select/decrement a deal.

