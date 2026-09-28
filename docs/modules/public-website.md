# Module: Public Website

## Purpose

Public marketing website for privatedeals.in (migrated from the standalone PrivatedealsWebsite project). Config-driven Blade pages with SEO meta, sitemap, and static theme assets. No database dependency for page content.

Admin (`/admin`) and APIs (`/api/*`) are unchanged and live in the same Laravel app.

## Entry points

- `routes/web.php` — marketing routes (`/`, `/about`, `/opportunities`, …), legacy redirects, `/sitemap.xml`
- Controller: `App\Http\Controllers\Web\Front\MarketingPageController`
- Config: `config/pages.php` (nav, per-page SEO, legacy HTML redirects, partner login URL)
- Support: `App\Support\Nav`, `App\View\Components\OptimizedImage`
- Views: `resources/views/marketing/` (`layouts`, `partials`, `pages`, `components`)
- Assets: `public/marketing/{assets,images,fonts,vendor}` (isolated from admin `public/assets/`)
- `public/robots.txt` — points at `https://privatedeals.in/sitemap.xml`

## Public URLs

| Path | Notes |
|------|--------|
| `/` | Home |
| `/about`, `/opportunities`, `/primary`, `/secondary`, `/unlisted` | Marketing pages |
| `/how-it-works`, `/contact` | Process + contact (form posts client-side to PrivateDeals API) |
| `/privacy-policy`, `/terms-conditions`, `/risk-disclosure`, `/disclaimer` | Legal |
| `/login` | 301 → `config('pages.partner_login_url')` |
| `/sitemap.xml` | Marketing sitemap from `pages` config |

Legacy redirects (301): HTML paths from old static site; also `/aboutus` → `/about`, `/contactus` → `/contact`, `/terms-of-use` → `/terms-conditions`, `/risk-disclouser` → `/risk-disclosure`.

## Features

- Per-page title/description/robots, canonical, Open Graph / Twitter tags in `marketing.layouts.app`
- GA4 + Facebook domain verification in layout
- JSON-LD Organization + WebSite on home
- WebP-aware `<x-optimized-image>`
- Contact form JS → `https://www.shuruup.com/api/privatedeals/submit-data`

## Intentionally disabled (files kept)

Previous `FrontWebsiteController` public site (`front.*` routes): company detail pages, DB contact form, team/download/CML cards, inquiry/beta endpoints. Controllers/views under `resources/views/front/website/` and `public/website-assets/` remain on disk but are not routed.

## Dependencies

- `isMaintenanceMiddleware` / `CoreMiddleware` on marketing routes
- Partner login is external (`partner.privatedeals.in`)

## Risks

- Do **not** put marketing CSS/JS into `public/assets/` — that folder is the admin Metronic theme.
- Marketing CSS uses relative `../fonts` and `../images` from `public/marketing/assets/main.css`; keep the `public/marketing/{assets,fonts,images,vendor}` layout.
- Changing paths in `config/pages.php` without matching Blade/nav updates breaks SEO and menus.
- Maintenance mode middleware can hide the marketing site.

## Related

- [modules/admin.md](admin.md)
- [api/overview.md](../api/overview.md)
