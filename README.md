# cafwebsite

The public marketing website for **Champion Auto Finance** (championautofinance.com),
version-controlled here so the pages can be edited in GitHub.

This is the **core site only** — the homepage, the main navigation pages, and the shared
theme. The hundreds of programmatic SEO landing pages, redirect stubs, and the WordPress
backend that also live on the production server are intentionally **not** included here.

## Contents

- `index.html` — homepage (copy of `caf-v7-assets/home.html`, served at `/` in production).
- `caf-v7-assets/` — the shared theme:
  - `styles.css`, `pages.css`, `caf-white.css`, `fonts.css`, `script.js`
  - `home.html` — source of the homepage
  - `assets/` — logos, mascots, hero/section images (jpg/webp/png)
  - `fonts/` — self-hosted Inter + Sora woff2
  - `lead.php` — homepage lead-form handler (PHP; runs on the production host)
- Navigation pages (each a static `index.html`):
  - `how-it-works/`, `faqs/`, `contact-us/`, `dealer-partners/`,
    `lease-buyouts-refinancing/`, `privacy-policy/`, `terms-of-service/`
- `contact-handler.php` — contact/dealer form handler (PHP; runs on the production host).
- `robots.txt`

## Deliberately excluded

- All WordPress core and data: `wp-admin/`, `wp-includes/`, `wp-content/`, `wp-*.php`,
  `wp-config.php` (DB credentials), and the database.
- All SEO landing pages and city/keyword pages (auto-refinance, lease-buyout, car-payment-help,
  get-out-of-a-car-lease, dealer-financing, etc.) and redirect pages.
- Private data: `form-submissions/` (customer leads / PII), `error_log`, `cgi-bin/`.
- SMTP credentials — these live outside the web root and are not in this repo.

## Two hosting notes

1. **PHP handlers don't run on GitHub Pages.** All the pages are static HTML and render
   fine on Pages. The two `*.php` files are the live form handlers — kept here as source;
   they only execute on the production PHP host (GoDaddy).

2. **Pages use root-absolute paths** (`/caf-v7-assets/…`, `/contact-us/`). They resolve
   correctly when the site is served at a domain **root**. On the project-Pages URL
   (`https://championautofinance.github.io/cafwebsite/`) those `/…` paths point above the
   repo and won't load. To render the Pages URL fully, attach a **custom domain** (served
   at root), or convert the internal paths to relative.

## Publishing

Edit here for version control. The production site runs on GoDaddy; deploy changes there
via SFTP (the established route for championautofinance.com).
