# App note — transaction documents list

This page is only the new `documents` key on Pre-IPO transaction list and detail. The rest of the order flow is in [order-flow-handoff.md](order-flow-handoff.md).

`payment_receipt` and `share_transfer_receipt` are unchanged. Use `documents` when the screen shows every file for the order in one list.

## Where it appears

The same object is on list, detail, and the success body of cancel, approve, reject, payment receipt, confirm payment, share-transfer receipt, and confirm share transfer.

| Who | List | Detail |
|---|---|---|
| Buying partner | `GET /api/v2/business/pre-ipo/transaction-list` | `GET /api/v2/business/pre-ipo/transaction/detail` |
| Institution | `GET /api/v2/business/institution/pre-ipo/transaction` | `GET /api/v2/business/institution/pre-ipo/transaction/detail` |

`documents` is always an array. It is `[]` until a file is stored. A file that is still waiting for a signature is not in the list.

## Item

| Field | Meaning |
|---|---|
| `id` | Document id |
| `type` | One of the values below |
| `name` | Label to show. Can be null |
| `path` | Storage path |
| `url` | Open or download this |

| `type` | When it appears |
|---|---|
| `BuyMandate` | After the investor signs the buy mandate |
| `Pre-IPO Deal Slip` | After the investor signs the deal slip |
| `Payment Receipt` | After the buying partner uploads the payment receipt |
| `Pre-IPO Share Transfer Receipt` | After the Institution uploads the share-transfer receipt |

Order is oldest first. Open the file with `url`.

`view_payment_receipt` still uses `payment_receipt.url`. `view_share_transfer_receipt` still uses `share_transfer_receipt.url`. Those two objects are also inside `documents`.

## Example

A completed order. Earlier steps omit the files that do not exist yet.

```json
{
  "status": 1,
  "message": "Transaction detail",
  "data": {
    "id": 965,
    "payment_receipt": {
      "id": 1438,
      "name": "Payment Receipt - OYO",
      "path": "preipo_transaction_payment_receipt/receipt.pdf",
      "url": "https://example.s3.amazonaws.com/preipo_transaction_payment_receipt/receipt.pdf"
    },
    "share_transfer_receipt": {
      "id": 1439,
      "name": "Share transfer receipt - OYO",
      "path": "preipo/transfer.pdf",
      "url": "https://example.s3.amazonaws.com/preipo/transfer.pdf"
    },
    "documents": [
      {
        "id": 1401,
        "type": "BuyMandate",
        "name": "Buy mandate - OYO",
        "path": "preipo/mandate.pdf",
        "url": "https://example.s3.amazonaws.com/preipo/mandate.pdf"
      },
      {
        "id": 1410,
        "type": "Pre-IPO Deal Slip",
        "name": "Deal slip - OYO",
        "path": "preipo/deal-slip.pdf",
        "url": "https://example.s3.amazonaws.com/preipo/deal-slip.pdf"
      },
      {
        "id": 1438,
        "type": "Payment Receipt",
        "name": "Payment Receipt - OYO",
        "path": "preipo_transaction_payment_receipt/receipt.pdf",
        "url": "https://example.s3.amazonaws.com/preipo_transaction_payment_receipt/receipt.pdf"
      },
      {
        "id": 1439,
        "type": "Pre-IPO Share Transfer Receipt",
        "name": "Share transfer receipt - OYO",
        "path": "preipo/transfer.pdf",
        "url": "https://example.s3.amazonaws.com/preipo/transfer.pdf"
      }
    ]
  }
}
```

Other keys on `data` are unchanged. This snippet shows only the file fields.
