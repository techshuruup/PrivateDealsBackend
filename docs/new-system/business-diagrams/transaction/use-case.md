# Use case — Transaction (buy)

Editable PlantUML: [plantuml/use-case-transaction.puml](plantuml/use-case-transaction.puml)

**Mapping:** Partner ≈ Wealth Manager app · Institution ≈ Private Deal Seller app. Seller in a deal may be Partner or Institution.

---

## Diagram

```mermaid
flowchart TB
  Partner((Partner buyer-side))
  Investor((Investor))
  Seller((Seller))

  subgraph SystemBox [System]
    UC1([Start buy for investor])
    UC2([Generate buy mandate])
    UC3([Sign mandate])
    UC4([Show mandate on seller dashboard])
    UC5([Accept mandate — confirm shares])
    UC6([Reject mandate — cancel transaction])
    UC7([Generate deal slip])
    UC8([Sign deal slip])
    UC9([Pay offline])
    UC10([Upload payment receipt])
    UC11([Confirm payment received])
    UC12([Upload share-transfer receipt])
    UC13([Confirm shares — complete transaction])
  end

  Partner --> UC1
  Partner --> UC10
  Partner --> UC13
  Investor --> UC3
  Investor --> UC8
  Investor --> UC9
  Seller --> UC5
  Seller --> UC6
  Seller --> UC11
  Seller --> UC12
  UC1 --> UC2
  UC2 --> UC3
  UC3 --> UC4
  UC5 --> UC7
  UC7 --> UC8
```

---

## Actors and use cases

| Actor | Use cases |
|-------|-----------|
| **Partner** (buyer-side) | Start buy for investor; upload payment receipt; confirm shares / complete |
| **Investor** | Sign mandate; sign deal slip; pay offline |
| **Seller** | Accept mandate; reject/cancel; confirm payment; upload share-transfer receipt |
| **System** | Generate mandate; generate deal slip; update statuses |

---

## Notes

- One **buy mandate** document from investor to seller party (not separate buy/sell agreements).
- After accept, deal slip is sent to the **investor** to sign.
- Status labels: see [README.md](README.md).

Related: [activity.md](activity.md) · [swimlane.md](swimlane.md)
