# subramanyam-agency

A marketing website and its content portal, written in plain PHP — no framework, no page builder, no WordPress. Built for a small agency site that needed a real CMS behind it and had to run on shared hosting.

![The content portal's dashboard](docs/images/dashboard.png)

<!-- Replace docs/images/dashboard.png — see docs/images/README.md for what to capture -->

## Why this exists

Client sites kept landing in the same trap: WordPress plus a page builder plus eight plugins, which is slow, fragile, and needs updating forever. The alternative usually offered is a headless CMS with a monthly bill and a hosting story the client can't afford.

This is the third option — a small PHP application with the CMS built in, deployable by file copy to a ₹300/month shared host, with no Composer step on the server and no npm build in production. It runs the site it was written for.

## Features

**Content**
- Page content stored as editable blocks, with drafts separate from published values
- Inline editing on the live site for signed-in staff, scoped to a preview mode
- Blog with categories, tags, scheduled publishing, and an RSS feed
- Services, case studies, FAQs, testimonials and client logos as managed resources
- Media library with automatic WebP variant generation and remote image import

**SEO**
- Per-post SEO analysis with scoring (`app/Core/SeoAnalyzer.php`)
- Generated `sitemap.xml` and `robots.txt`, rebuilt on publish
- Per-page title and meta description control

**Security**
- Application-level firewall with rule management and an event log
- Rate limiting with IP addresses hashed before storage
- CSRF tokens, nonces, and a security-headers middleware including CSP
- HTML sanitisation through HTMLPurifier on every rich-text field
- Spam guard on public forms
- `scripts/security-audit.php` — a pre-deploy check that exits non-zero on failure

**Operations**
- Token-authenticated REST API (see [API.md](API.md))
- First-party traffic tracking — visitors, paths, referrers — with no third-party script
- Activity log of admin actions
- Appearance settings: typography picker, branding, site logo and favicon
- Page cache with asset fingerprinting

## Quick start

Requires PHP 8.2+ with `pdo_mysql`, `mbstring`, `gd`, `fileinfo`, `openssl`, and MySQL 8 or MariaDB 10.4+. Composer is needed for local development only — **not** on the server.

```bash
git clone https://github.com/Subramanyam2312/subramanyam-agency.git
cd subramanyam-agency
composer install
cp .env.example .env
```

Generate an application key and paste it into `APP_KEY`:

```bash
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

Fill in the `DB_*` values, then build the schema and load demo content:

```bash
php database/migrate.php --seed
```

Create your account — there is deliberately no public sign-up route:

```bash
php scripts/create-admin.php
```

Run it:

```bash
php -S localhost:8130 -t public public/index.php
```

The site is at `http://localhost:8130`, the portal at `/admin`.

For local development set `MAIL_DRIVER=log`, which writes rendered emails to `storage/logs/mail.log` instead of sending them.

> `scripts/serve.sh` automates all of the above including a local MySQL, but it downloads a ~250 MB MySQL build on first run. The manual steps above avoid that.

## Configuration

| Variable | Purpose | Note |
|---|---|---|
| `APP_ENV` | `local` or `production` | |
| `APP_DEBUG` | Error display | Must be `false` in production |
| `APP_URL` | Canonical base URL | Must agree with `.htaccess` on `www` |
| `APP_KEY` | 64 hex chars | HMAC key for hashing IPs before storage |
| `APP_TIMEZONE` | PHP and MySQL timezone | |
| `DB_HOST` | Database host | `localhost` on most shared hosts |
| `DB_PORT` | Database port | |
| `DB_DATABASE` | Database name | |
| `DB_USERNAME` | Database user | |
| `DB_PASSWORD` | Database password | |
| `SESSION_NAME` | Session cookie name | |
| `MAIL_DRIVER` | `smtp` or `log` | `log` writes to a file instead of sending |
| `MAIL_HOST` | SMTP host | |
| `MAIL_PORT` | SMTP port | 587/`tls` or 465/`ssl` |
| `MAIL_USERNAME` | SMTP user | |
| `MAIL_PASSWORD` | SMTP password | |
| `MAIL_ENCRYPTION` | `tls` or `ssl` | |
| `MAIL_FROM_ADDRESS` | Sender | Must be on your own domain or DMARC drops it |
| `MAIL_TO_ADDRESS` | Where form notifications go | |
| `SECURITY_HSTS` | HSTS toggle | `false` until HTTPS is proven |
| `SECURITY_HSTS_MAX_AGE` | HSTS lifetime | Start at `300` |

Changing `APP_KEY` later only invalidates existing rate-limit buckets, which is harmless.

## Architecture

```
app/
├── Config/        configuration loaders
├── Controllers/   Site (public), Admin (portal), Api
├── Core/          router, database, auth, firewall, SEO, media, mail
├── Middleware/    CSRF, auth, firewall, security headers, page optimise
├── Models/        data access
├── Support/       helpers
└── Views/         templates
database/migrations/   18 numbered SQL files, applied in filename order
public/                the only web-accessible directory
scripts/               admin creation, mail test, scheduled publish, security audit
```

Routing is a small custom router; there is no framework. Only two runtime dependencies: `ezyang/htmlpurifier` and `phpmailer/phpmailer`.

`public/index.php` probes one directory up for `app/`, then two, so the same code runs locally and in a shared-hosting layout where `app/` sits above the web root, with no edit.

`vendor/` is committed deliberately — Composer is not guaranteed to exist on shared hosting, so dependencies deploy by file copy.

Deployment is covered in [DEPLOY.md](DEPLOY.md), with a host-agnostic version at [hostinger-php-deploy](https://github.com/Subramanyam2312/hostinger-php-deploy).

## Limitations

- **Single-site.** No multi-tenancy, no site switcher.
- **No automated tests.** Verified by hand and by `scripts/security-audit.php`. This is the biggest gap.
- **No build pipeline.** Tailwind compiles through a standalone binary; there is no bundler, and no CI.
- **MySQL/MariaDB only.** Raw SQL migrations, no query-builder abstraction, no Postgres or SQLite support.
- **Migrations are forward-only.** There are no down migrations and no rollback.
- **Seed content is generic demo copy**, not the live site's content.
- **`scripts/security-audit.php` needs a working `.env` and database** — it will not run on a fresh clone until you have completed the quick start.
- **English-only admin.** No i18n layer.
- **Deploy is a file copy.** No zero-downtime deploy and no rollback mechanism; take a backup first.

## License

MIT — see [LICENSE](LICENSE).

---

Built by [Subramanyam M N](https://subramanyammn.in).
