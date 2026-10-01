# Feature plan: Pre-IPO partner order notifications

**Status:** Implemented with the `order_step` flow. Where this page says a new WhatsApp template is required, the code sends the in-app notification and logs `Pre-IPO WhatsApp template is not configured`. It does not invent a template id. Existing template names listed below are sent. Demo investors (`is_demo = 1`) skip every send in this matrix.

Events below key off `order_step` in [workflows/pre-ipo-order-steps.md](../workflows/pre-ipo-order-steps.md). They do not key off integer `status` `0`–`5`. Old orders (`order_step` null) keep today’s status-based sends. New orders (`order_step` set) use this matrix only.

## How messages are sent today

Pre-IPO events use one pattern:

1. **WhatsApp.** `UtillsHelper::sendWpMessage` with `NotificationTypeEnum::event`, a provider template name, and `WpMessageTypeEnum::text`. A sign link is passed in the click-URL list (the deal slip passes `documents_signers.link`). The helper records the message for the WhatsApp dispatcher.
2. **In-app.** `UtillsHelper::sendNotification` writes `NotificationsModel` for a user id and model class (`InvestorModel`, `PartnerModel`, or `UserAdminModel`), with a title and a body built by `UtillsHelper::paramsToTemplate`.
3. **Signer SMS.** Deal-slip Digio requests set `send_sign_link` and `notify_signers`. That notification is Digio’s, not an app SMS template and not `SMSHelper`. This plan uses that same Digio notify for the mandate and the deal slip. App WhatsApp templates are listed separately below.

Demo investors (`is_demo = 1`) are skipped by the live Pre-IPO sends. Keep that skip.

“New template required” means no current WhatsApp template name matches the audience and the wording. In-app copy does not have a template id.

Admin WhatsApp that already fires on older orders (commit, cancel, bank-details copy) stays on the old status path. Those jobs must not run for a row where `order_step` is set. Admin is not a new recipient in this matrix. Admin visibility of the order is covered in the workflow plan.

Self-investor orders (`investor.is_self = 1` on the buying partner, not the Institution `seller_investor_id`): the investor still receives the sign message, and the partner order payload also includes the sign link. See the workflow plan.

The new flow sends the mandate at `mandate_pending` and the deal slip at `deal_slip_pending` without waiting for `investor.preipo_kyc_status`. The live KYC reminder `preipo_deal_kycpending_sun2` stays only for old orders.

## Matrix

### `mandate_pending` — order placed, buy mandate generated

| Who | Channel | Intent | Template |
|-----|---------|--------|----------|
| Investor | WhatsApp | The buy order was created. Sign the mandate. Company, quantity, and price are included. The button is the Digio sign link. | New template required |
| Investor | Digio signer notify | Digio sends the sign link to the investor mobile. | No app template. Same `notify_signers` flag as the deal slip. |
| Investor | In-app | Same intent as the WhatsApp: mandate is ready to sign. | In-app copy, no template id |
| Buying partner | In-app | Mandate was sent to the investor by SMS and WhatsApp. Ask them to sign. On a self-investor order the sign link is also on the order in the partner app. | In-app copy, no template id |
| Institution | None | The order is hidden until the mandate is signed. | None |

The live investor commit template `preipo_commit_investor_2702_sun` confirms that a transaction was created. It does not carry a mandate sign link, so it does not cover this step. Do not send it as the mandate message.

### `cancelled`

Partner cancel from `mandate_pending`, or Institution/admin reject from `share_confirmation_pending`. The reason is in the message.

| Who | Channel | Intent | Template |
|-----|---------|--------|----------|
| Investor | WhatsApp and in-app | The order was cancelled. Include the transaction, company, quantity, price, and the reason. | Existing WhatsApp `notify_investor_transaction_cancelled_sun`, already sent from `PreIpoTransactionHelper::cancelTransactionNotification` with that wording |
| Buying partner | In-app | The order was cancelled and why. Skip this when the partner themselves cancelled; they already entered the reason. Send it when the Institution or admin rejects. | In-app copy, no template id |
| Institution | None on a cancel from `mandate_pending` | They never saw the order. | None |
| Institution | None on their own reject | The reject action is theirs. The order stays on their list as cancelled, with the reason. | None |

`notify_admin_transaction_cancelled_sun` stays the existing admin copy on the old status path. It is not reused for the Institution partner.

### `share_confirmation_pending` — mandate signed

| Who | Channel | Intent | Template |
|-----|---------|--------|----------|
| Investor | None | Signing was the investor’s action. | None |
| Buying partner | In-app | The mandate is signed. Share confirmation is pending with the Institution. | In-app copy, no template id |
| Institution | WhatsApp and in-app | A mandate is signed on your deal. Approve the shares or cancel with a reason. | New template required |

This is the first message the Institution receives for the order.

### `deal_slip_pending` — deal slip generated

| Who | Channel | Intent | Template |
|-----|---------|--------|----------|
| Investor | WhatsApp | The deal slip is ready. Sign it from the button. | Existing `preipo_on_dealslip_investor_sun_1`. Live body: the transaction was approved, the deal slip was generated, please sign. Click URL is the signer link. |
| Investor | Digio signer notify | Digio sends the sign link. | No app template |
| Investor | In-app | Same as the WhatsApp: review and sign the deal slip. | Existing in-app copy paired with `preipo_on_dealslip_investor_sun_1` |
| Buying partner | In-app | The deal slip was sent to the investor by SMS and WhatsApp. Ask them to sign. On a self-investor order the sign link is also on the order in the partner app. | In-app copy, no template id |
| Institution | In-app | The deal slip was generated. Waiting for the investor to sign. | In-app copy, no template id |

Send this when approve moves `share_confirmation_pending` to `deal_slip_pending`. Do not wait for `preipo_kyc_status`, and do not send `preipo_deal_kycpending_sun2` on this path.

### `payment_pending` — deal slip signed, payment details sent

Payment is outside the system. Bank lines come from the Institution self investor’s `user_bank_accounts` row (account holder, bank name, account number, IFSC). There is no branch. If that row has no account number, the message says payment is due and omits the account lines.

| Who | Channel | Intent | Template |
|-----|---------|--------|----------|
| Investor | WhatsApp and in-app | The deal slip is signed. Pay this amount to the following account. Include holder, bank, account number, and IFSC when the CML bank row has them. | New template required |
| Buying partner | In-app | Payment details were sent to the investor. The partner can view the same details on the order. Next step is to upload the payment receipt once the investor has paid. | In-app copy, no template id |
| Institution | In-app | The deal slip is signed and payment details were sent. Waiting for payment confirmation. | In-app copy, no template id |

`investor_bankdetails_for_transaction` is the live investor bank WhatsApp, and `pre_ipo_bank_details_admin_sun_copy_copy` is the live admin copy. Both are filled from `seller_master`, including branch and the seller company name. They stay on the old status path. The new flow does not reuse them, because the account is the CML `user_bank_accounts` row and there is no branch.

The old deal-slip webhook (`PreIpoTransactionHelper::changeTransactionStatus`) sends that seller-master bank message when it sets status `3`. New orders must not go through that branch.

### `payment_confirmation_pending` — payment receipt uploaded

| Who | Channel | Intent | Template |
|-----|---------|--------|----------|
| Investor | None | The partner uploads the receipt. | None |
| Buying partner | In-app | The receipt is uploaded. Payment confirmation is pending. | In-app copy, no template id. The partner just uploaded it; WhatsApp is omitted. |
| Institution | WhatsApp and in-app | A payment receipt is ready. View or download it and confirm the payment. | New template required |

The investor upload path today does not send this message (the admin notify there is commented out).

### `share_transfer_pending` — payment confirmed

| Who | Channel | Intent | Template |
|-----|---------|--------|----------|
| Investor | WhatsApp and in-app | Payment was received. Share transfer is pending. | Existing `transaction_payment_received_sun`. Live body includes the transaction, company, quantity, and the settlement date. |
| Buying partner | In-app | Payment was received. Waiting for the share transfer. | In-app copy, no template id |
| Institution | None | They confirmed the payment. Their next step is on the order: upload the share-transfer receipt. | None |

### `share_transfer_confirmation_pending` — share-transfer receipt uploaded

| Who | Channel | Intent | Template |
|-----|---------|--------|----------|
| Investor | None | Confirmation is the buying partner’s action. | None |
| Buying partner | WhatsApp and in-app | The share-transfer receipt is ready to view or download. Confirm the share transfer. | New template required |
| Institution | In-app | The receipt is uploaded. Waiting for the partner to confirm. | In-app copy, no template id |

### `completed` — partner confirms the share transfer

The order is completed and the shares are added to the investor’s Pre-IPO portfolio (`UtillsHelper::preIpoPortfolio`) on this transition.

| Who | Channel | Intent | Template |
|-----|---------|--------|----------|
| Investor | WhatsApp and in-app | The share transfer is complete. Holdings are in the portfolio. | Existing `transaction_completed_sun` |
| Buying partner | In-app | The transaction is completed. | In-app copy, no template id |
| Institution | In-app | The transaction is completed. | In-app copy, no template id |

## New WhatsApp templates to approve

| `order_step` | Recipient | Intent |
|---------------|-----------|--------|
| `mandate_pending` | Investor | Sign the buy mandate (button = Digio link). |
| `share_confirmation_pending` | Institution | Approve the shares or cancel with a reason. |
| `payment_pending` | Investor | Pay using the CML bank lines, or a shorter line when the account is missing. |
| `payment_confirmation_pending` | Institution | Open the receipt and confirm payment. |
| `share_transfer_confirmation_pending` | Buying partner | Open the receipt and confirm the share transfer. |

Existing templates this plan reuses on the new flow: `notify_investor_transaction_cancelled_sun`, `preipo_on_dealslip_investor_sun_1`, `transaction_payment_received_sun`, `transaction_completed_sun`.

## Related

- [workflows/pre-ipo-order-steps.md](../workflows/pre-ipo-order-steps.md)
- [features/pre-ipo.md](pre-ipo.md)
- [features/notifications-comms.md](notifications-comms.md)
