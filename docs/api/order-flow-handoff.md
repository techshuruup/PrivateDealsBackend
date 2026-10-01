# App handoff — Pre-IPO order flow

Read this for the new Pre-IPO buy order only. Login is the existing partner login in [app-handoff.md](app-handoff.md). This page does not cover company create, deals, CML, or the investor list.

Base path: `/api`. Every request needs header `headtoken`. After login, also send `Authorization: Bearer <token>` (the partner token).

Success is HTTP 200 with JSON `status` `1`. A business failure is also HTTP 200 with `status` `0` and `message`. Show `message` to the user. A missing or invalid `headtoken` is rejected before that JSON, as HTTP 500 with `message` `Unauthorized Request`.

## Who is who

| Who | Role in this order |
|---|---|
| Buying partner | The logged-in partner who places the order for an investor. They see the order from the moment it is created, including while the mandate is waiting to be signed. |
| Institution | The partner who owns the sell deal (`partner.type` is `Institution`). They see the order only after the investor has signed the buy mandate. |
| Investor | The client on the order. They sign the buy mandate and later the deal slip by opening the link in SMS or WhatsApp. They pay the bank account outside the app. |

`partner_id` on the order is the Institution, not the buying partner. `seller_id` stays null. The buying partner is the partner who owns the investor.

The Institution’s own investor (`seller_investor_id`) is the source of the bank account on the payment step. That person does not sign the mandate or the deal slip.

## Place the order

`POST /api/v2/business/pre-ipo/buy`

Any logged-in partner can call this. The body is JSON.

```json
{
  "orders": [
    {
      "deal_uuid": "9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d",
      "investor_id": 22,
      "shares": 10,
      "share_price": 102
    }
  ]
}
```

`orders` is required and must contain at least one item. Each item needs `deal_uuid` (the sell-deal `uuid` from the company list), `investor_id`, `shares` (integer, at least 1), and `share_price` (the price the partner types). The app already has the investor id and the sell-deal uuid. Do not send `deal_id`, `partner_id`, `seller_id`, `base_price`, or `distributer_price`. The server ignores those if they are sent.

The deal must be an available, unexpired sell deal created by an Institution, with a base price. The investor must belong to this partner (a client, a relation manager’s client, or this partner’s self investor). Shares must be at least the deal minimum. When the deal has a positive available quantity, the shares in this request, added together for the same deal, must not exceed it. The available quantity is not reduced. One failed item inserts nothing. `share_price` is not checked against the deal price.

### The three prices

Example. The Institution typed 99 when the deal was created. That is `base_price`. The server had already added the processing fee, so the deal’s customer price is 100. The order stores that 100 as `distributer_price`. The buying partner types 102 in `share_price`. 102 is what the investor pays per share.

On 10 shares the server stores `payable_amount` and `investment_amount` as 1020 (10 × 102). The partner’s margin is 102 − 100 = 2 per share. The processing fee sits inside `distributer_price` (100 − 99).

### What the server stores

Each item becomes one order at `order_step` `mandate_pending`. Integer `status` stays `0` for the whole new flow. Do not read `status`.

| Saved field | Value |
|---|---|
| `partner_id` | The Institution that created the deal |
| `seller_investor_id` | That Institution’s self investor |
| `seller_id` | null |
| `investor_id` | The investor on the item |
| `company_id` | The deal’s company |
| `deal_id` | The deal |
| `shares` | The quantity sent |
| `base_price` | 99 in the example, from the deal |
| `distributer_price` | 100 in the example, the deal price (base plus fee) |
| `share_price` | 102 in the example, the price the partner typed |
| `payable_amount` | Shares × `share_price` |
| `order_step` | `mandate_pending` |

The server also sets instrument `equity`, payment mode `RTGS`, and an invoice number.

Success message is `Orders placed successfully.` when every buy mandate was sent. If a mandate send fails, the order is still saved and the message is `Orders placed. The buy mandate could not be sent.` Each row in `data` includes `mandate_sent` (`true` or `false`) plus the same order fields as the list. The investor is asked to sign by SMS and WhatsApp. There is no sign API for the app to call.

## Order steps

Read `order_step`. The sentences to show are `current` (current step) and `next` (next step). The buttons to show are the names in `action`. `action` is null when that user waits. `next` is null on a cancelled order, and the string `N/A` when the order is completed.

The Institution list and detail omit `mandate_pending`. A cancel from `mandate_pending` stays off the Institution list. A reject after the mandate is signed stays on the Institution list with the reason.

| `order_step` | Buying partner | Institution |
|---|---|---|
| `mandate_pending` | Current: Transaction initiated. Buy mandate generated. Next: Ask the investor to sign the mandate sent by SMS and WhatsApp. Button: Cancel. If this investor is the partner’s self investor, also show the sign link. | The order is hidden. No screen, no button. |
| `cancelled` | Current: Transaction cancelled, plus the reason. Next: none. No button. | The same text, only if they could already see the order (they rejected it). A cancel while the mandate was still pending stays hidden. No button. |
| `share_confirmation_pending` | Current: Mandate signed. Next: Share confirmation pending. No button. | Current: Mandate signed. Next: Approve the transaction or cancel with a reason. Buttons: Approve, Reject. |
| `deal_slip_pending` | Current: Deal slip generated. Next: Ask the investor to sign the deal slip sent by SMS and WhatsApp. If this investor is the partner’s self investor, show the sign link. | Current: Deal slip generated. Next: Waiting for the deal slip signature. No button. |
| `payment_pending` | Current: Deal slip signed. Payment details sent to the investor. Next: Upload the payment receipt. Buttons: view payment details, upload the receipt. | Current: same. Next: Waiting for payment confirmation. No button. |
| `payment_confirmation_pending` | Current: Payment receipt uploaded. Next: Payment confirmation pending. No button. | Current: Payment receipt uploaded. Next: View or download the receipt and confirm the payment. Buttons: view the receipt, confirm payment. |
| `share_transfer_pending` | Current: Payment received. Next: Waiting for share transfer. No button. | Current: Payment received. Next: Upload the share-transfer receipt. Button: upload the receipt. |
| `share_transfer_confirmation_pending` | Current: Share transfer receipt uploaded. Next: Confirm the share transfer. Buttons: view the receipt, confirm share transfer. | Current: Share transfer receipt uploaded. Next: Waiting for share transfer confirmation. No button. |
| `completed` | Current: Transaction completed. Next: N/A. No button. | The same. Shares are on the investor’s Pre-IPO portfolio. |

`action` names, in the order the server sends them:

| `action` value | Who | What the app does |
|---|---|---|
| `cancel` | Buying partner | Open cancel and send a reason |
| `show_mandate_sign_link` | Buying partner, self investor only | Open `sign_link` |
| `show_deal_slip_sign_link` | Buying partner, self investor only | Open `sign_link` |
| `view_payment_details` | Buying partner | Show `payment_details` |
| `upload_payment_receipt` | Buying partner | Upload the payment receipt |
| `approve` | Institution | Approve |
| `reject` | Institution | Reject with a reason |
| `view_payment_receipt` | Institution | Open `payment_receipt.url` |
| `confirm_payment` | Institution | Confirm payment |
| `upload_share_transfer_receipt` | Institution | Upload the share-transfer receipt |
| `view_share_transfer_receipt` | Buying partner | Open `share_transfer_receipt.url` |
| `confirm_share_transfer` | Buying partner | Confirm the share transfer |

## Fields the app reads

List and detail return the saved order plus the step fields. Other columns can still be on the object. Drive the screen from the fields below.

Do not use `status_list` or `current_status`. Do not use integer `status`. The current-step sentence is `current`. The next-step sentence is `next`. The old status sentence that used to arrive as `next_step` is omitted on these orders. Use `order_step`, `current`, `next`, and `action`.

The buying partner’s list (`GET /api/v2/business/pre-ipo/transaction-list`) can still include old orders that have no `order_step`. Those rows still carry `status_list`. Skip them on the new order screens.

| Field | Where | Meaning |
|---|---|---|
| `id` | List and detail | Send this as `transaction_id` on later calls |
| `order_step` | List and detail | The step value in the table above |
| `current` | List and detail | Sentence for the current step |
| `next` | List and detail | Sentence for the next step, or null, or `N/A` |
| `action` | List and detail | Button names, or null |
| `sign_link` | List and detail | Digio URL for a self-investor order that is still waiting for a signature. Otherwise null |
| `investor` | List and detail | On the list: `id`, `name`, `is_self`, `partner_id`. Detail includes the rest of the investor record. Read `name` and `is_self` |
| `company` | List and detail | On the list: `id`, `uuid`, `brand_name`, `logo`. Detail includes the rest of the company record. Read `brand_name` and `logo` |
| `shares` | List and detail | Quantity |
| `base_price` | List and detail | Deal base (99 in the example) |
| `distributer_price` | List and detail | Deal price after the fee (100 in the example) |
| `share_price` | List and detail | Price the partner typed and the investor pays (102 in the example) |
| `payable_amount` | List and detail | Amount due: shares × `share_price` |
| `cancellation_reason` | List and detail | The reason after cancel or reject. Empty until then |
| `payment_details` | Detail only | From `payment_pending` through `completed`. Null before that. `amount` is `payable_amount`. `account` is the Institution self investor’s bank (`account_holder_name`, `bank_name`, `account_number`, `ifsc_code`), or null when that account is missing |
| `payment_receipt` | List and detail | After the partner uploads it: `id`, `name`, `path`, `url`. Otherwise null |
| `share_transfer_receipt` | List and detail | After the Institution uploads it: `id`, `name`, `path`, `url`. Otherwise null |
| `documents` | List and detail | Every stored file in one list. See [transaction-documents-handoff.md](transaction-documents-handoff.md) |

Open a receipt with `url`.

Buying partner list: `GET /api/v2/business/pre-ipo/transaction-list`. Optional query `skip` (default 0) and `take` (default is the app page size; a value below 1 becomes 15). Newest first. Message `Transaction List`.

Buying partner detail: `GET /api/v2/business/pre-ipo/transaction/detail?transaction_id=481`. The investor must belong to this partner, and `order_step` must be set. Message `Transaction detail`.

Institution list: `GET /api/v2/business/institution/pre-ipo/transaction`. No paging query. Only orders whose `partner_id` is this Institution, past `mandate_pending`, excluding a cancel that happened before the mandate was signed. Message `Transaction list`. A partner who is not an Institution gets `status` `0` and `Only Institution partners can manage these orders.`

Institution detail: `GET /api/v2/business/institution/pre-ipo/transaction/detail?transaction_id=481`. Same visibility. An order they cannot see returns `Transaction not found`.

Detail while the investor still needs to pay, for the buying partner:

```json
{
  "status": 1,
  "message": "Transaction detail",
  "data": {
    "id": 481,
    "order_step": "payment_pending",
    "current": "Deal slip signed. Payment details sent to the investor.",
    "next": "Upload the payment receipt.",
    "action": ["view_payment_details", "upload_payment_receipt"],
    "sign_link": null,
    "investor": { "id": 22, "name": "Client Name", "is_self": 0 },
    "company": { "id": 12, "brand_name": "Example Co", "logo": "company/logo.png" },
    "shares": 10,
    "base_price": "99.00",
    "distributer_price": "100.00",
    "share_price": "102.00",
    "payable_amount": "1020.00",
    "cancellation_reason": null,
    "payment_details": {
      "amount": "1020.00",
      "account": {
        "account_holder_name": "Institution Self",
        "bank_name": "HDFC Bank",
        "account_number": "1234567890",
        "ifsc_code": "HDFC0000123"
      }
    },
    "payment_receipt": null,
    "share_transfer_receipt": null,
    "documents": []
  }
}
```

Prices come back as stored decimals. `sign_link` is a URL only on a self-investor order at `mandate_pending` or `deal_slip_pending`.

## Actions

Send `transaction_id` as the order `id`. A call at the wrong step returns `status` `0` and `This action is not available for the current order step.` The step does not change. An unknown order, or an Institution order that is still hidden, returns `Transaction not found`.

| Method | Path | Body | Allowed when | After success |
|---|---|---|---|---|
| POST | `/api/v2/business/pre-ipo/transaction/cancel` | JSON `transaction_id`, `reason` (required, max 1000 characters) | Buying partner, `mandate_pending` | `order_step` `cancelled`. `cancellation_reason` is the reason. Message `Transaction cancelled.` |
| POST | `/api/v2/business/pre-ipo/transaction/payment-receipt` | `multipart/form-data`: `transaction_id`, `file` (png, jpg, jpeg, or pdf, max `file_document_max_size` MB) | Buying partner, `payment_pending` | `order_step` `payment_confirmation_pending`. Message `Payment receipt uploaded.` |
| POST | `/api/v2/business/pre-ipo/transaction/confirm-share-transfer` | JSON `transaction_id` | Buying partner, `share_transfer_confirmation_pending` | `order_step` `completed`. Shares are added to the investor portfolio. Message `Transaction completed.` |
| POST | `/api/v2/business/institution/pre-ipo/transaction/approve` | JSON `transaction_id` | Institution, `share_confirmation_pending` | Deal slip is sent. `order_step` `deal_slip_pending`. Message `Deal slip sent.` If the slip cannot be sent, the step stays `share_confirmation_pending` and the message is `The deal slip could not be sent.` |
| POST | `/api/v2/business/institution/pre-ipo/transaction/reject` | JSON `transaction_id`, `reason` (required, max 1000 characters) | Institution, `share_confirmation_pending` | `order_step` `cancelled`. `cancellation_reason` is the reason. Message `Transaction cancelled.` |
| POST | `/api/v2/business/institution/pre-ipo/transaction/confirm-payment` | JSON `transaction_id` | Institution, `payment_confirmation_pending` | `order_step` `share_transfer_pending`. Message `Payment confirmed.` |
| POST | `/api/v2/business/institution/pre-ipo/transaction/share-transfer-receipt` | `multipart/form-data`: `transaction_id`, `file` (png, jpg, jpeg, or pdf, max `file_document_max_size` MB) | Institution, `share_transfer_pending` | `order_step` `share_transfer_confirmation_pending`. Message `Share-transfer receipt uploaded.` |

Each success `data` object is the detail payload, so the screen can replace the order it is showing.

Institution calls also require `partner.type` `Institution`.

## Self investor

When the order’s investor is the buying partner’s self investor (`investor.is_self` is `1`), the investor still receives the mandate link and the deal-slip link by SMS and WhatsApp. The partner app also shows that link.

`sign_link` is set only for that partner, and only while `action` contains `show_mandate_sign_link` or `show_deal_slip_sign_link`. Open `sign_link`. If `mandate_sent` was false, `sign_link` can be null because the link was never created. The Institution payload always has `sign_link` null.

## What the app does not build

The investor signs on the Digio page. The app opens `sign_link`. It does not host a signing screen and it does not call a sign API.

The investor pays the account in `payment_details` outside the app. The app shows those bank lines and later uploads the receipt. It does not move the money.

`GET /api/v2/seller/pre-ipo/transaction` and `GET /api/v2/seller/pre-ipo/transaction/detail` stay on the old seller app. They were not copied. New orders leave `seller_id` null, so those URLs do not return this flow. The Institution uses `/api/v2/business/institution/pre-ipo/transaction`.
