# Living Documentation Rules (Permanent)

This folder is the **living source of truth**. Code is ultimate truth; docs must be updated to match code — never the reverse unless explicitly requested.

## Required check on every development task

```text
CODE CHANGE
    ↓
Does this change affect documented behavior?
    ↓
YES → Update relevant documentation in the same task
    ↓
NO → Continue
    ↓
Verify docs still accurate
```

## When you MUST update docs

- New/changed/removed features, APIs, DB tables/columns, models, services, jobs, schedules
- Auth / permission / role changes
- Integrations, webhooks, env/config, deployment steps
- Workflow or business-rule changes
- Major UI route changes for admin/front
- Deprecations

## New feature checklist

1. Identify affected doc areas  
2. Create feature/module doc if missing  
3. Update architecture / database / API / workflow docs as needed  
4. Update `docs/README.md` index  
5. Link related features and list business rules + risks  
6. Cite real file paths  

## Modification rule

Update the **canonical** document. Do not create `feature-x-v2.md` duplicates.

## Partner API pages

A new or changed route under `/api/v2/business/` that any logged-in partner can call is written in [api/partner.md](../api/partner.md) in the same task: one table row, then the request, rules, and saved fields.

Also add a short line in [api/v2.md](../api/v2.md) that points at `partner.md`. The field-level copy stays on `partner.md`.

Institution-only routes (`/api/v2/business/institution/...`) stay in [api/institution.md](../api/institution.md). Do not put those on `partner.md`.

If the call changes an order step or a saved column, update the matching workflow or feature page in the same task.

## Removal / deprecation

Remove dead references; mark deprecated only when historical context helps; update diagrams/index.

## Before significant changes

Read docs first → plan → implement → docs impact → update → verify.

## AI agents

Treat this file as a standing project rule for PrivateDeals V1. Completing a code task without the docs impact check is incomplete work when behavior changed.
