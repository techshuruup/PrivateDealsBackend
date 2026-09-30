# Use cases — Admin

Admin is an **external supporting actor**. The Admin application is outside this project’s available scope. Admin can **view all data**.

Editable PlantUML: [../plantuml/use-case-admin.puml](../plantuml/use-case-admin.puml)

---

## Diagram (Mermaid)

```mermaid
flowchart LR
  Admin((Admin))

  subgraph AdminApp [Admin application — outside scope]
    UC1([Create Wealth Manager])
    UC2([Create Seller])
    UC3([Create Distributor])
    UC4([Create Retailer])
    UC8([Create Institution])
    UC5([Assign WM investment areas])
    UC6([Create company])
    UC7([View all data])
  end

  Admin --> UC1
  Admin --> UC2
  Admin --> UC3
  Admin --> UC4
  Admin --> UC8
  Admin --> UC5
  Admin --> UC6
  Admin --> UC7
```

---

## Responsibilities (confirmed)

| Use case | Notes |
|----------|--------|
| Create Wealth Manager | Yes — CML KYC is required and saved on the self investor. If KYC fails, creation rolls back. |
| Create Seller | Yes |
| Create Distributor | Yes — parent **not** mandatory. CML KYC is required and saved on the self investor. |
| Create Retailer | Yes — parent **not** mandatory. CML KYC is required and saved on the self investor. |
| Create Institution | Yes — same admin form as Distributor, optional Wealth Manager parent, plus required CML KYC saved on the self investor. Institution can create an unlisted or secondary company that is approved and live for partners immediately, and can use the Institution company catalog, submissions, promoters, shareholders, and deals APIs. Seller share-price quotes stay on the seller app. Partner API cannot create the Institution account. |
| Assign WM investment areas | Primary / LP Secondary / Unlisted — one, two, or all |
| Create company | Shared company records |
| View all data | Yes |

**Out of current Admin scope:** create Investors; create Relationship Managers.

---

## PlantUML source

```plantuml
@startuml
left to right direction
actor "Admin" as Admin
rectangle "Admin application (outside scope)" {
  usecase "Create Wealth Manager" as UC1
  usecase "Create Seller" as UC2
  usecase "Create Distributor" as UC3
  usecase "Create Retailer" as UC4
  usecase "Assign WM investment areas" as UC5
  usecase "Create company" as UC6
  usecase "View all data" as UC7
}
Admin --> UC1
Admin --> UC2
Admin --> UC3
Admin --> UC4
Admin --> UC5
Admin --> UC6
Admin --> UC7
note right of UC3
  Parent not mandatory
end note
@enduml
```
