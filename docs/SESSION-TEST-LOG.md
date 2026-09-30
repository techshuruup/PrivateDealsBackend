# Session test log

15 tests passed. They used the app database inside transactions and left no rows.

Commands:

- php artisan route:list --path=institution
- php artisan route:list --path=v2/business/investor
- php artisan route:list --path=v2/business/kyc (no routes)
- php artisan test --filter=SessionBuiltFeaturesTest (13 passed)
- php artisan test --filter=ProcessingFeeRulesTest (2 passed)

Emails, mobiles, CINs, and database ids were generated at runtime. Only the fixed values below were asserted.

## Processing fee

| Setting | CommonHelper::processingFeePercentage() |
|---|---|
| 1 | 1.0 |
| 1.5 | 1.5 |
| 100 | 100.0 |
| 0 | fallback 1.0 |
| 101 | fallback 1.0 |
| nope | fallback 1.0 |
| missing | fallback 1.0 |

POST /admin/settings with processing_fee_percentage:

- Accepts: 1, 1.5, the string 1.5, 100
- Rejects: 0, the string 0, 101, the string 101

## Institution type and routes

- PartnerTypeEnum::institution value is Institution
- Routes exist: admin.partner.institution.list, create, view, edit, store, update, destroy
- POST api/v2/business/investor/kyc/cml/read name v2.business.investor.kyc.cml.read
- POST api/v2/business/investor/kyc/cml/save name v2.business.investor.kyc.cml.save
- No route URI contains v2/business/kyc

## Company create is approved immediately

Shared payload: type=unlisted, about=Session test company, min_investment_amount, market_cap, pe_ratio, pb_ratio, debt_to_equity, roe, book_value, face_value all 1, lot_size=1, sector=first master_sectors id where is_deleted=0.

| Actor | Endpoint | HTTP | JSON status | Saved |
|---|---|---|---|---|
| Institution | POST /api/v2/business/institution/company | 200 | 1 | approval_status=approved, status=0, is_deleted=0, submitted_by_partner_id set, submitted_by_seller_id null |
| Wealth manager on that URL | same | 200 | 0 | company not created |
| Seller | POST /api/v2/seller/company | 200 | 1 | approval_status=approved, status=0, is_deleted=0, submitted_by_seller_id set, submitted_by_partner_id null |

Seller seed used for login, not the assertion target: PAN ABCDE1234F, DP IN123456, client 12345678, bank Test Bank, account 1234567890, IFSC HDFC0001234, branch Main, password secret-pass.

## Deals and history (fee 2.5 percent)

Formula: share_price = round(base * (1 + 2.5/100), 2). Example: 3 * 1.025 = 3.075, saved as 3.08.

Pre-existing rows that must not become today's history:

- sell share_price 1, base 1, min_qty 1, qty 1, is_hot_deal false, is_deleted true, fee 2.5
- sell share_price 2, base 2, min_qty 1, qty 1, is_hot_deal false, expired yesterday, fee 2.5

Hot create POST /api/v2/business/institution/company/deals:

- Input: deal_type=sell, available_quantity=5, share_price=3, minimum_qty=1, is_hot_deal=true
- HTTP 200, JSON status 1
- Saved base_price 3.0, share_price 3.08
- CompanySharePrice count for that company = 0
- CalcuatePricingAutoJob not dispatched

Bulk POST /api/v2/business/institution/company/deals/bulk:

| Row | Input | Result |
|---|---|---|
| sell | company_id empty, sell_price empty, min_qty empty | skipped |
| sell | sell_price 0, min_qty 1 | skipped |
| sell | sell_price blank, min_qty 1 | skipped |
| sell | sell_price 100, min_qty 2 | created: base 100.0, share 102.50, minimum_qty 2 |
| sell | sell_price 80, min_qty 1 | created: base 80.0, share 82.00, processing_fee_percentage 2.5 |
| buy | buy_price 0, min_qty 1 | skipped |
| buy | buy_price 50, min_qty 4 | created: base 50.0, share 51.25 |

HTTP 200, JSON status 1. Exactly 3 new non-hot deals. created_by_partner_id = institution, created_by_seller_id null. Order of types: sell, sell, buy.

Today's history is 1 row:

- price 82.00
- distributer_price 51.25
- base_price 80.00

CalcuatePricingAutoJob is dispatched.

Second bulk: sell_price 200, min_qty 1, buy empty. JSON status 1, deal count +1, previous deal ids still exist. History stays 1 row and price stays 82.00.

Later requests:

- sell_price 10, min_qty 0: HTTP 200, JSON status 0, message contains min_qty
- empty sell price and min qty plus buy_price 0: HTTP 200, JSON status 0

Buy-only history, fee set back to 1 percent. Bulk buys only: buy_price 40 min_qty 1, and buy_price 25 min_qty 1. HTTP 200, JSON status 1. One history row from the lower buy:

- price 25.25 (25 * 1.01)
- distributer_price 25.25
- base_price 25.0

## Self investor

createSelfInvestor() called twice. Password is Hash::make of secret-pass.

| Partner type | is_self=1 investors | Copied fields |
|---|---|---|
| wealthmanager, distributor, retailer, institution | exactly 1 | name, mobile_number, email, password equal the partner; is_self=1 |
| relationmanager | 0 | |

Partner API create POST /api/v1/business/channel-partner/create. Parent is a wealth manager with commission 10.

| Field | Retailer child | RM child | API institution |
|---|---|---|---|
| partner_type | retailer | relationmanager | institution |
| name | Retail Child | RM Child | API Institution |
| password | secret-pass | secret-pass | secret-pass |
| gender | Male | Male | Male |
| commission | 4 | omitted | 1 |
| parent_partner_id | parent id | parent id | omitted |
| is_primary_access / is_secondary_access / is_preipo_access | 1 / 0 / 0 | 1 / 0 / 0 | 1 / 0 / 0 |
| JSON status | 1 | 1 | 0 |
| Result | partner saved, 1 self investor, 0 demat rows | partner saved, 0 investors | partner not saved |

Update POST /admin/partner/retailers/update/{uuid}: same retailer fields, name=Retail Child Updated, no password. Name saved as Retail Child Updated. Self-investor count stays 1.

Login and profile:

- POST /api/v1/business/login as wealth manager: mobile_no=partner mobile, password=secret-pass, firebase_token=test-token, device_id=device-{id}, device=android. HTTP 200, status 1, data.self_investor_id = that partner's is_self=1 investor id
- GET /api/v1/business/profile as that partner: HTTP 200, same self_investor_id
- Same login as relation manager, device_id=device-rm-{id}: HTTP 200, status 1, data.self_investor_id null
- GET profile as RM: HTTP 200, data.self_investor_id null

## Admin create CML

Admin payload: name={type} Create, gender=Male, password=secret-pass, is_primary_access=1, is_secondary_access=0, is_preipo_access=0, commission=3 except relation manager (no commission).

- POST /admin/partner/create for wealthmanager, distributor, retailer, institution with no CML: redirect, errors on cml_file and kyc_name, partner not saved
- POST /admin/partner/relation-manager/save with parent_partner_id of a distributor whose commission is 10: redirect, no cml_file error, partner saved, 0 investors

Successful institution POST /admin/partner/institution/save extra fields:

- dp_id IN123456
- client_id 99887766
- pan_no ABCDE1234F
- kyc_name KYC Person Name
- account_number 123456789012
- ifsc_code HDFC0001234
- bank_name HDFC
- cml_file uploaded PDF

Expected: redirect, no cml_file error, partner saved, exactly 1 self investor whose name is KYC Person Name (not the partner name). Demat row dp_id IN123456. DB transaction level at partner insert and demat insert is at least 2.

Blades that include admin.pages.partner.child.cml-kyc: wealthmanger/create, distributor/create, retailers/create, institution/create. relationalmanager/create does not. Rendered partial HTML contains name="kyc_name" and name="cml_file".

## Investor list

Actors: wealth manager commission 5, relation manager parent_id = that WM, another distributor.

| Name | Partner | is_self | preipo_kyc_status | is_active |
|---|---|---|---|---|
| Self Person | WM | 1 | 0 | 1 |
| Client Person | WM | 0 | 1 | 1 |
| Inactive Person | WM | 0 | 1 | 0 |
| RM Client | RM | 0 | 1 | 1 |
| Stranger | other distributor | 0 | 1 | 1 |

GET /api/v1/business/investor and /api/v2/business/investor with is_kyc=All&is_active=Yes&is_aif=All. Both HTTP 200, JSON status 1.

- v2 first id is Self Person
- v2 ids after the first are the same set as all v1 ids
- v1 ids do not include Self Person
- v2 contains Client Person and RM Client
- v2 excludes Inactive Person and Stranger
- v2 rows after the first have is_self=0

Second query is_kyc=Yes&is_active=All: Self Person absent (kyc 0), Client Person present.

Admin investor list: institution partner. Both investors preipo_kyc_status=1, is_active=1, is_demo=0, is_blocked=0. One is_self=1, one is_self=0. Admin role=admin, name=Test Admin, password secret-pass. GET /admin/investor/active. Self investor is not in the list. Client is in the list.

## CML partner API

Caller is a wealth manager. Also an RM whose parent_id is that WM, and a distributor.

| Investor | Result for the WM |
|---|---|
| Original Self, WM, is_self=1, kyc 0 | read allowed |
| Original Client, WM, is_self=0, kyc 0 | save allowed |
| RM client, is_self=0, kyc 0 | save allowed |
| deleted, WM, is_deleted=1 | reject |
| other distributor client, is_self=0 | reject |
| other distributor self, is_self=1 | reject |
| RM own self, is_self=1 | reject |

- POST cml/save with empty body: HTTP 200, status 0, message contains investor
- POST cml/read with empty body: HTTP 200, status 0
- read or save on each forbidden id: status 0, message exactly Investor not found, no demat row
- Forbidden save body still rejected: dp_id IN000001, client_id 11111111, pan_no ABCDE1234F, name Should Not Save

Allowed saves:

| Investor | dp_id | client_id | pan | name | Result |
|---|---|---|---|---|---|
| Original Client | IN000002 | 22222222 | ABCDE1234F | Saved Client | HTTP 200, status 1, demat investor_id = client, name becomes Saved Client, preipo_kyc_status=1 |
| RM client | IN000003 | 33333333 | ABCDE1234F | Saved RM Client | status 1, demat row exists |

Read self: POST cml/read with investor_id = self and a generated CML PDF. If the PDF fails to parse, the read assertion is skipped. When parse succeeds: JSON status 1, preipo_kyc_status stays 0, name stays Original Self.

PDF text: DP ID: 12345678 Client ID: 99887766 First Holder Name: TEST USER PAN ABCDE1234F Bank A/c No 123456789012 IFSC Code: HDFC0001234.

## Not executed

- CalcuatePricingAutoJob::handle() was not run. Dispatch was verified. The bus was faked so a sync run would not reprice every company.
- No admin browser login. Admin validation, the investor list, and the create blades were exercised without a session form submit.
- Buy-a-deal and the deal slip were not tested.
