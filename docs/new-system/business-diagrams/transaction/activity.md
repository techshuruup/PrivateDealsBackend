# Activity — Transaction (buy)

Editable PlantUML: [plantuml/activity-transaction.puml](plantuml/activity-transaction.puml)

Single end-to-end activity (no swimlanes). Reject path ends in cancelled.

---

## Diagram

```mermaid
flowchart TD
  Start([Start]) --> Buy[Partner selects Investor and clicks Buy]
  Buy --> S1[Status: Mandate generated — signature pending]
  S1 --> SignM[Investor signs mandate]
  SignM --> S2[Status: Share confirmation pending — next NA for investor]
  S2 --> SellerSees[Seller sees request on dashboard]
  SellerSees --> Decide{Accept or Reject?}
  Decide -->|Reject| Cancelled([Transaction cancelled])
  Decide -->|Accept| ConfirmShares[Seller confirms shares]
  ConfirmShares --> S3[Status: Deal slip generated]
  S3 --> SignSlip[Investor signs deal slip]
  SignSlip --> S4[Status: Deal slip signed — next pay offline]
  S4 --> Pay[Investor transfers amount offline]
  Pay --> UploadPay[Partner uploads payment receipt]
  UploadPay --> S5[Status: Payment receipt uploaded — payment verification pending]
  S5 --> SellerPay[Seller confirms payment received]
  SellerPay --> S6[Status: Share transfer pending]
  S6 --> UploadXfer[Seller uploads share-transfer receipt]
  UploadXfer --> S7[Status: Share transfer receipt uploaded — waiting for confirmation]
  S7 --> PartnerConfirm[Partner confirms shares]
  PartnerConfirm --> Done([Transaction completed])
```

---

## Status checkpoints

1. Mandate generated — signature pending  
2. Share confirmation pending (seller Accept/Reject)  
3. Deal slip generated — investor signs  
4. Deal slip signed — investor pays; Partner uploads receipt  
5. Payment receipt uploaded — payment verification pending  
6. Share transfer pending — seller uploads share-transfer receipt  
7. Share transfer receipt uploaded — waiting for confirmation  
8. Transaction completed  

Related: [use-case.md](use-case.md) · [swimlane.md](swimlane.md) · [README.md](README.md)
