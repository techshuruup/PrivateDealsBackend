# Module: Admin

## Purpose

Back-office operations for PrivateDeals: users, companies, transactions, KYC reviews, CMS, broadcasts, masters, system settings.

## Entry points

- Routes: `routes/web.php` → `Route::prefix('admin')->name('admin.')`
- Controllers: `app/Http/Controllers/Web/Admin/**`
- Views: `resources/views/admin/**`
- Middleware: session `admin` guard; `hasPermission:{permission string}`
- Auth: `Web\Admin\Auth\AuthenticatedSessionController`

## Responsibilities

- Partner CRUD by type (wealth manager, distributor, institution, retailer, relation manager). Institution uses `admin/partner/institution` and the same fields as Distributor, including an optional Wealth Manager parent.
- Investor management, manual KYC / AIF review
- Primary / secondary / Pre-IPO transaction ops + document upload
- Company master (prices, OCR import, WhatsApp report PDFs). Pending list `/admin/company/pending-seller` still lists `approval_status=pending` seller and Institution submissions (`submitted_by_partner_id`). New seller and Institution creates are approved immediately and do not land on this list.
- AI AutoWork (`/admin/ai-autowork`) — Company Ingest inbox + per-job AI guides
- Coupons, BSE holidays, seller master, portfolios
- Masters (geo, banks, sectors, industries, header tokens, …)
- CMS pages/media, WhatsApp + push broadcasts
- App version / build / global settings. Processing fee % is `app_settings.processing_fee_percentage` (default 1, min 1, max 100) on `/admin/system-configuration/system-settings`.
- Manager / master-admin user management

## Important files

| Area | Path |
|------|------|
| Dashboard | `Web\Admin\Dashboard\DashboardController` |
| Investors | `Web\Admin\InvestorController` |
| Companies | `Web\Admin\CompanyController` |
| Pre-IPO tx | `Web\Admin\PreIpoTransactionController` |
| Primary tx | `Web\Admin\PrimaryTransactionController` |
| Secondary tx | `Web\Admin\SecondaryTransactionController` |
| Permissions MW | `AdminPermissionsMiddleware` |
| Theme | `app/Core/*`, `ThemeHelper` |

## Database / models

Heavy use of: `InvestorModel`, `PartnerModel`, `StartupModel`, `CompanyModel`, `PreIpoModel`, `PrimaryTransactionModel`, `SecondaryTransactionModel`, `DocumentsModel`, master_* models, report/message models.

## Business rules

- Permission strings in routes must match rights stored for admin users (`AdminRightsModel` / Spatie permission config — verify before renaming).
- Document uploads often trigger helper status transitions (Digio-related).
- Company share-price OCR and partner WhatsApp PDF sending are admin-only operational tools with side effects (queue jobs).
- Company list more-options **Share Prices** is a list/filter/delete modal for `company_share_price` history (AJAX). It is separate from sidebar **Update Share Price**.

## Fresh start command

`php artisan system:fresh --force`

One command resets operational data on the database of the machine where it runs, then deletes the matching files on the local disk and the S3 bucket.

It deletes investors, partners, sellers, startups, transactions, KYC, documents, notifications, API logs, webhook logs, WhatsApp/SMS/email logs, error logs, admin tracking, website form submissions, sessions, and queued jobs. It also deletes `storage/logs/laravel*.log`.

It keeps admin users and their roles, `app_settings`, header API tokens, API clients, master data, coupons, and the company catalog (companies, deals, promoters, shareholders, prices, news, events, and company files). Partner and seller ids on `company` and `company_deals` are set to null. Broadcast rows stay, with investor and partner id lists cleared.

Without `--force` the command exits and changes nothing. It is not part of deploy.

## Common modification points

- New admin screen: controller + Blade view + `routes/web.php` + breadcrumb in `routes/breadcrumbs.php` + permission gate.
- Changing list filters: often DataTables (`app/DataTables`, yajra).

## Risks

- Large controllers; prefer targeted methods.
- Permission typos silently block routes.
- Export/Excel (`maatwebsite/excel`) and DomPDF paths for reports.

## Related docs

- [features/pre-ipo.md](../features/pre-ipo.md)
- [features/primary-transactions.md](../features/primary-transactions.md)
- [authentication/overview.md](../authentication/overview.md)
