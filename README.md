# Agency website and CMS

Public marketing site plus a custom content portal. PHP 8.2+ and MySQL, no framework,
no page builder, no third-party CMS.

Deployment to Hostinger shared hosting is covered separately in `DEPLOY.md` (Phase 7).
API documentation lands in `API.md` (Phase 4).

---

## Requirements

| | |
|---|---|
| PHP | 8.2 or newer, with `pdo_mysql`, `mbstring`, `gd`, `fileinfo`, `openssl` |
| Database | MySQL 8 or MariaDB 10.4+ |
| Composer | Local development only — **not** required on the server |
| Node | Not required anywhere. Tailwind builds via a standalone binary. |

---

## Local setup

```bash
composer install
cp .env.example .env
```

Generate a key and paste it into `APP_KEY`:

```bash
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

`APP_KEY` is the HMAC key used to hash IP addresses before storage. Changing it later
only invalidates existing rate-limit buckets, which is harmless.

Fill in the `DB_*` values, then create the schema and load placeholder content:

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

The admin portal is at `/admin`. For local development set `MAIL_DRIVER=log`, which
writes rendered emails to `storage/logs/mail.log` instead of sending them.

---

## Stylesheet

Tailwind compiles to a single static file through the standalone CLI, so no Node
runtime and no `node_modules` are involved. Fetch the binary once:

```bash
curl -sSL -o resources/bin/tailwindcss https://github.com/tailwindlabs/tailwindcss/releases/download/v3.4.17/tailwindcss-macos-arm64
```

Swap `macos-arm64` for `macos-x64` or `linux-x64` as needed, then:

```bash
chmod +x resources/bin/tailwindcss && ./resources/build-css.sh
```

Use `./resources/build-css.sh --watch` while working on templates.

The compiled `public/assets/css/app.css` **is** committed, because deploying to shared
hosting is a file copy with no build step at the far end.

---

## Layout

```
app/
├── Config/       app.php, database.php, mail.php, security.php, session.php, routes.php
├── Core/         Router, Request, Response, View, Database, Model, Auth, Csrf,
│                 Validator, RateLimiter, Mailer, Slugger, ActivityLogger, Env, Config
├── Controllers/  Admin/ (portal), Site/ (Phase 5), Api/ (Phase 4)
├── Middleware/   SecurityHeaders, VerifyCsrf, RequireAuth, RequireAdmin
├── Models/
├── Support/      helpers.php
└── Views/        layouts/, partials/, admin/, emails/, errors/
database/
├── migrations/   applied in filename order, tracked in a `migrations` table
├── seed.sql
└── migrate.php
public/           ← web root: index.php, .htaccess, assets/, uploads/
resources/        Tailwind source, config and build script
scripts/          CLI entry points
storage/          logs, cache, sessions — writable, never web-accessible
```

`app/`, `storage/`, `vendor/` and `database/` sit **outside** the web root. On Hostinger
the contents of `public/` become `public_html/`, and `public/index.php` probes one and
then two levels up for `app/`, so the same codebase runs locally and deployed with no edit.

---

## Migrations

```bash
php database/migrate.php            # apply pending
php database/migrate.php --status   # list applied and pending
php database/migrate.php --seed     # apply, then load seed.sql
php database/migrate.php --fresh    # drop everything and re-run (APP_ENV=local only)
```

Migrations are plain SQL applied in filename order and recorded by name, so re-running
is safe. MySQL commits DDL implicitly — a failed migration cannot roll back, so the
runner stops at the first error and prints the exact file and statement.

`seed.sql` assumes empty tables. It inserts posts with no author, because on a fresh
install no user exists yet; the first admin created afterwards adopts them.

Media and client logos are **not** seeded — a media row has to point at a file that
really exists, and seeding one would produce broken images.

---

## Conventions

- `snake_case` in the database, `camelCase` in JavaScript, `PascalCase` for classes
- Rows are plain associative arrays, not entity objects
- Every value reaching SQL is a bound parameter; identifiers are validated against
  `^[A-Za-z_][A-Za-z0-9_]*$` and back-quoted
- Every dynamic value printed in a template goes through `e()`
- Rich text is sanitized on **save**, not on render

---

## Security notes

- Passwords hashed with argon2id, rehashed transparently when parameters change
- CSRF token required on every mutating request; API is exempt because it uses
  bearer tokens rather than cookies
- Login is throttled per IP+email **and** locked per account, so rotating IPs does not help
- Nonce-based CSP with no `unsafe-inline` — which is why nothing loads from a CDN
- Sessions are stored in `storage/sessions`, not the world-readable system default
- IP addresses are stored only as HMACs
- HSTS stays off until HTTPS is confirmed working on the live domain

---

## What is here

**Public site** — home, services index and detail, work index and case studies,
about, FAQ, blog with category filtering and search, post pages, contact, privacy,
terms, RSS. Every string on every page comes from the CMS.

**Content portal** at `/admin` — posts (rich text, scheduling, tags, per-post SEO),
categories, services with per-service FAQs, case studies, testimonials, FAQs,
timeline, client logos, page copy, media library, enquiry inbox, newsletter
subscribers, settings, users and API tokens.

**REST API** at `/api/v1` — token-authenticated, so an external agent can publish
here. See `API.md`.

---

## Commands

```bash
php database/migrate.php --seed     # schema + placeholder content
php scripts/create-admin.php        # first account (no public sign-up exists)
php scripts/security-audit.php      # 26 checks; exits non-zero on failure
php scripts/publish-scheduled.php   # cron: publish due posts, sweep rate limits
./resources/build-css.sh            # compile Tailwind
```

Run the security audit before every deploy. It boots the real router and asserts
that no `/admin` route is reachable without a session, that settings and users
additionally require the admin role, that every `/api` route needs a bearer token,
and that every mutating non-API route verifies CSRF — checks that catch a route
written into the wrong group, which is how admin pages leak.

---

## Build status

| Phase | Scope | State |
|---|---|---|
| 1 | Stack, structure, schema | Done — `PHASE-1-PLAN.md` |
| 2 | Scaffold, migrations, seed, auth, admin shell | Done |
| 3 | CMS content modules, media library, settings | Done |
| 4 | REST API + `API.md` | Done |
| 5 | Public site | Done |
| 6 | SEO, schema, performance, accessibility | Done |
| 7 | Security review, `DEPLOY.md` | Done |

### Known gaps

- **Lighthouse has not been run.** There was no Chrome binary in the build
  environment. Page weight, contrast, heading order and blocking-resource counts
  were measured directly instead. Run it against the live domain after deploy.
- **No hero video file.** The mechanism is built and wired to a CMS field; set
  Page copy → Hero → video URL and it attaches after load, desktop only, never
  under reduced motion or on a metered connection. Until then the hero runs on a
  CSS motion layer.
- **Privacy and terms are a starting point, not legal advice.** They describe what
  this application actually does. Have them reviewed.
- **Critical CSS is not inlined.** Deliberate — the reasoning is in the layout head.
- **Client logos and media are not seeded**, because a media row must point at a
  file that exists. Upload real assets before the logo marquee appears.
