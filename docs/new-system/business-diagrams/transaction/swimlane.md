# Swimlane activity — Transaction (buy)

Editable PlantUML: [plantuml/swimlane-transaction.puml](plantuml/swimlane-transaction.puml)

Lanes: **Partner** · **Investor** · **Seller** · **System**

---

## Diagram

```mermaid
flowchart TB
  subgraph PartnerLane [Partner buyer-side]
    P1[Select Investor and click Buy]
    P2[Upload payment receipt]
    P3[Confirm shares received]
    P1 --> P2
    P2 --> P3
  end

  subgraph InvestorLane [Investor]
    I1[Sign buy mandate]
    I2[Sign deal slip]
    I3[Transfer amount offline]
    I1 --> I2 --> I3
  end

  subgraph SellerLane [Seller]
    Se1[See mandate on dashboard]
    Se2{Accept or Reject?}
    Se3[Reject — cancel]
    Se4[Accept — confirm shares]
    Se5[Wait for deal slip signature]
    Se6[Confirm payment received]
    Se7[Upload share-transfer receipt]
    Se1 --> Se2
    Se2 -->|Reject| Se3
    Se2 -->|Accept| Se4
    Se4 --> Se5
    Se5 --> Se6 --> Se7
  end

  subgraph SystemLane [System]
    Y1[Mandate generated — signature pending]
    Y2[Share confirmation pending]
    Y3[Deal slip generated]
    Y4[Deal slip signed]
    Y5[Payment receipt uploaded — verification pending]
    Y6[Share transfer pending]
    Y7[Share transfer receipt uploaded — waiting confirmation]
    Y8[Transaction completed]
    Y9[Transaction cancelled]
  end

  P1 --> Y1
  Y1 --> I1
  I1 --> Y2
  Y2 --> Se1
  Se3 --> Y9
  Se4 --> Y3
  Y3 --> I2
  I2 --> Y4
  Y4 --> I3
  I3 --> P2
  P2 --> Y5
  Y5 --> Se6
  Se6 --> Y6
  Y6 --> Se7
  Se7 --> Y7
  Y7 --> P3
  P3 --> Y8
```

---

## Lane summary

| Lane | Responsibilities |
|------|------------------|
| **Partner** | Start buy; upload payment receipt; final share confirmation |
| **Investor** | Sign mandate; sign deal slip; pay offline |
| **Seller** | Accept/Reject; wait for slip signature; confirm payment; upload share-transfer receipt |
| **System** | Status transitions and deal slip generation |

Related: [activity.md](activity.md) · [use-case.md](use-case.md) · [README.md](README.md)
