# Phase 1 — Stack, Structure & Schema

Status: **awaiting approval**. No application code written yet.

Confirmed in Phase 0:

| Question | Answer |
|---|---|
| Hosting | Hostinger **shared** (Premium/Business) |
| Brand kit | None — three directions to be proposed, client picks |
| CMS users | **Multi-user with roles** (`admin`, `editor`) |
| Publishing | **Scheduled publishing** (cron-driven) |

Still outstanding: agency name, exact domain spelling, form-delivery email. None of these affect the schema.

---

## 1. Stack decision

**PHP 8.2 + MySQL 8 / MariaDB, custom micro-MVC. No framework.**

### Why no framework

Laravel on Hostinger shared is possible but a bad trade: it wants `artisan` (SSH is not guaranteed on all shared tiers), writable `bootstrap/cache` and `storage` with correct ownership, a queue worker for anything async, and it drags ~120 packages into `vendor/` that must be committed because Composer may not exist on the server. Slim would give routing and PSR-7 and nothing else — about 200 lines of the work here.

What actually gets built instead is small and auditable: a front controller, a regex router, a thin PDO layer, plain PHP templates with a mandatory escaping helper, and a middleware pipeline. That is roughly 1,200 lines of framework, all of it code we control, all of it deployable by dragging a folder into hPanel's File Manager.

### Dependencies (Composer, `vendor/` committed to git)

| Package | Purpose | Note |
|---|---|---|
| `ezyang/htmlpurifier` | Sanitize rich-text HTML **on save** | Pure PHP, no exotic extensions. Needs a writable cache dir → `storage/purifier/`. Whitelist-based, so it strips `<script>`, `on*=` handlers and `javascript:` URLs by construction rather than by blacklist. |
| `phpmailer/phpmailer` | SMTP delivery | PHP `mail()` is not used anywhere. |
| `vlucas/phpdotenv` | `.env` loading | |

That is the whole list. Image resizing is ~130 lines of GD (`imagewebp` is available on Hostinger) rather than pulling in Intervention. Slugs, UUID-ish tokens, validation, rate limiting and the migration runner are all first-party.

### Frontend

- **Tailwind CSS v3.4 via the standalone CLI binary.** No Node, no `node_modules`, no build step on the server. `resources/css/app.css` → `public/assets/css/app.css`, committed. Critical above-the-fold CSS is inlined into the layout; the rest is loaded `media="print" onload`-style.
- **Vanilla JS, ES modules, no bundler.** Scroll-reveal, marquee, accordion, slider, mobile drawer, contact form — each a small deferred module. Everything behind `prefers-reduced-motion`.
- **Quill 2.0 (self-hosted) as the rich-text editor, not TipTap.** TipTap is npm/ESM-only and needs a bundler, which breaks the "no Node in production" rule. Quill 2 ships a single self-hostable JS + CSS file. Your brief permitted either. Output is HTML, sanitized server-side on save by HTMLPurifier.

### What is explicitly *not* used

CDN Tailwind, jQuery, GSAP, Bootstrap, any page builder, any third-party CMS, any CDN-loaded JS at all (CSP is nonce-based and blocks external script origins).

---

## 2. Folder structure

```
subramanyam-agency/
├── app/                          # never web-accessible
│   ├── Config/
│   │   ├── app.php               # env-driven config arrays
│   │   ├── routes.php            # all routes, one file, readable top to bottom
│   │   └── purifier.php
│   ├── Core/
│   │   ├── Router.php  Request.php  Response.php  View.php
│   │   ├── Database.php          # PDO singleton, prepared statements only
│   │   ├── Model.php             # base: find/where/paginate/softDelete
│   │   ├── Auth.php  Csrf.php  RateLimiter.php  Validator.php
│   │   ├── Mailer.php            # PHPMailer wrapper, SMTP
│   │   ├── ImageProcessor.php    # GD → WebP, multi-width
│   │   ├── Sanitizer.php         # HTMLPurifier wrapper
│   │   ├── Slugger.php  Sitemap.php  ActivityLogger.php  Schema.php (JSON-LD)
│   ├── Middleware/
│   │   ├── SecurityHeaders.php  CsrfMiddleware.php
│   │   ├── AuthMiddleware.php  RoleMiddleware.php
│   │   ├── ApiTokenMiddleware.php  RateLimitMiddleware.php
│   ├── Models/                   # one per table
│   ├── Controllers/
│   │   ├── Site/                 # Home About Services Blog Faq Contact Legal
│   │   ├── Admin/                # Dashboard Post Category Tag Service Faq
│   │   │                         # Testimonial CaseStudy Timeline Logo
│   │   │                         # PageBlock Media Submission Subscriber
│   │   │                         # Seo Setting User ApiToken Auth
│   │   └── Api/V1/               # Post Media Category Tag
│   └── Views/
│       ├── layouts/  partials/  site/  admin/  emails/  errors/
├── database/
│   ├── migrations/               # 0001_... .sql, applied in order
│   ├── seed.sql                  # realistic placeholder content
│   └── migrate.php               # runner, tracks in `migrations` table
├── public/                       # ← THIS is the web root
│   ├── index.php                 # front controller
│   ├── .htaccess
│   ├── robots.txt  sitemap.xml   # both generated
│   ├── uploads/                  # + .htaccess killing PHP execution
│   └── assets/{css,js,img,fonts,video}
├── storage/                      # outside web root, writable
│   ├── cache/  logs/  sessions/  purifier/
├── scripts/
│   ├── publish-scheduled.php     # cron */5
│   └── regenerate-sitemap.php    # cron daily
├── resources/
│   ├── css/app.css               # Tailwind source
│   ├── tailwind.config.js
│   └── build-css.sh              # standalone CLI invocation
├── vendor/                       # committed
├── .env.example  .gitignore  composer.json
└── README.md  DEPLOY.md  API.md
```

### The Hostinger document-root problem

Hostinger shared serves `~/domains/<domain>/public_html` and does **not** reliably let you point the doc root at a subdirectory. So the deployed layout is not the repo layout:

```
~/domains/<domain>/
├── app/  database/  storage/  vendor/  scripts/   ← app source, above the web root
└── public_html/                                    ← contents of repo's public/
    ├── index.php  .htaccess  assets/  uploads/
```

`public/index.php` resolves the app root via a single constant that checks for `../app` and then `../../app`, so the identical codebase runs locally (with `public/` as root) and on Hostinger (with `public_html/` as root) without edits. `DEPLOY.md` will spell this out with the exact File Manager steps.

---

## 3. Database schema

InnoDB, `utf8mb4_0900_ai_ci` (falls back to `utf8mb4_unicode_ci` on MariaDB). Every table has `created_at`; mutable tables have `updated_at`; content tables that would hurt to lose have `deleted_at` (soft delete, and every query scopes `deleted_at IS NULL`). All FKs declared with explicit cascade behaviour.

**Convention:** `snake_case` columns, singular FK names (`author_id`), `*_media_id` for anything pointing at the media library, `sort_order INT` for anything hand-orderable.

### Auth & access

**`users`**
`id` · `name` · `email` UNIQUE · `password_hash` (argon2id) · `role` ENUM('admin','editor') · `avatar_media_id` FK→media SET NULL · `is_active` · `last_login_at` · `failed_attempts` TINYINT · `locked_until` · `reset_token_hash` · `reset_expires_at` · timestamps · `deleted_at`
Index: `email`, `role`.

**`remember_tokens`** — separate table so "remember me" works per-device and all sessions can be revoked.
`id` · `user_id` FK CASCADE · `token_hash` UNIQUE · `expires_at` · `ip_hash` · `created_at`

**`api_tokens`**
`id` · `user_id` FK CASCADE · `name` · `prefix` CHAR(8) (shown in the UI so a token is identifiable) · `token_hash` UNIQUE (SHA-256; the plaintext is displayed exactly once at creation) · `abilities` JSON (`["posts:write","media:write"]`) · `last_used_at` · `expires_at` NULL · `revoked_at` NULL · `created_at`
Index: `token_hash`, `user_id`.

**`rate_limits`** — DB-backed because there's no Redis on shared hosting.
`id` · `bucket` VARCHAR(191) UNIQUE (e.g. `login:<ip_hash>`, `api:<token_id>`, `contact:<ip_hash>`) · `hits` · `window_start` · `expires_at`
Index: `expires_at` (swept on write).

**`migrations`** — `id` · `filename` UNIQUE · `applied_at`

### Blog

**`categories`** — `id` · `name` · `slug` UNIQUE · `description` · `meta_title` · `meta_description` · `sort_order` · timestamps · `deleted_at`

**`tags`** — `id` · `name` · `slug` UNIQUE · `created_at`

**`posts`**
`id` · `title` · `slug` UNIQUE · `excerpt` · `content` LONGTEXT (sanitized HTML) · `content_text` LONGTEXT (plain-text mirror, written on save — powers search and reading time without parsing HTML at query time) · `featured_media_id` FK SET NULL · `category_id` FK SET NULL · `author_id` FK→users SET NULL · `status` ENUM('draft','scheduled','published') · `published_at` DATETIME NULL · `reading_time` SMALLINT · `views` INT · `is_featured` · `meta_title` · `meta_description` · `og_media_id` FK SET NULL · `canonical_url` · `noindex` TINYINT · timestamps · `deleted_at`
Indexes: `slug`, composite `(status, published_at)` — the hot path for every listing query — `category_id`, `author_id`. FULLTEXT on `(title, excerpt, content_text)` for blog search.

**`post_tags`** — `post_id` FK CASCADE · `tag_id` FK CASCADE · PK(`post_id`,`tag_id`) · index on `tag_id`

### Services

**`services`**
`id` · `title` · `slug` UNIQUE · `icon` VARCHAR(60) (key into an inline SVG sprite — no icon-font, no network request) · `short_description` · `hero_headline` · `hero_subheadline` · `problem_statement` TEXT · `includes` JSON (bullet list) · `process` JSON (`[{title,description}]`) · `deliverables` JSON · `content` LONGTEXT · `image_media_id` FK SET NULL · `sort_order` · `is_featured` · `is_active` · `meta_title` · `meta_description` · `og_media_id` · `noindex` · timestamps · `deleted_at`

JSON is used for the repeatable bullet/step lists because they are edited as a unit in one admin form and never queried across rows. Per-service FAQs get a real table because they are individually orderable and feed `FAQPage` JSON-LD.

**`service_faqs`** — `id` · `service_id` FK CASCADE · `question` · `answer` TEXT · `sort_order` · timestamps

### Other content

**`faqs`** — `id` · `question` · `answer` TEXT · `group_name` · `sort_order` · `is_active` · timestamps · `deleted_at`

**`testimonials`** — `id` · `quote` TEXT · `author_name` · `author_role` · `company` · `media_id` FK SET NULL (headshot or client logo) · `rating` TINYINT NULL · `is_featured` · `sort_order` · `is_active` · timestamps · `deleted_at`

**`case_studies`** — `id` · `title` · `slug` UNIQUE · `client_name` · `industry` · `challenge` TEXT · `solution` TEXT · `results` TEXT · `metrics` JSON (`[{label,value}]` — the "+240% organic traffic" tiles) · `cover_media_id` FK SET NULL · `gallery` JSON (media ids) · `service_id` FK SET NULL · `status` ENUM('draft','published') · `published_at` · `is_featured` · `sort_order` · `meta_*` · `noindex` · timestamps · `deleted_at`

**`timeline_entries`** — `id` · `year` VARCHAR(9) (string, so "2019–21" works) · `title` · `description` TEXT · `sort_order` · `is_active` · timestamps

**`client_logos`** — `id` · `name` · `media_id` FK RESTRICT · `link_url` · `sort_order` · `is_active` · timestamps

**`page_blocks`** — the "never touch code to change copy" table.
`id` · `page_key` (`home`,`about`,`contact`,`global`) · `block_key` (`hero_headline`,`stat_projects_value`…) · `label` (human text shown in admin) · `type` ENUM('text','textarea','html','number','image','url') · `value` TEXT · `media_id` FK SET NULL · `group_name` · `sort_order` · `updated_by` FK→users SET NULL · timestamps
UNIQUE(`page_key`,`block_key`). Seeded with every editable string on Home/About/Contact, so the admin screen renders itself from the table rather than from a hardcoded form.

### Media

**`media`** — `id` · `filename` (randomized, `bin2hex(random_bytes(16))` + extension) · `original_name` · `path` · `mime` (from `finfo` sniffing, not the extension) · `size` · `width` · `height` · `alt_text` · `caption` · `variants` JSON (`{"320":"...webp","640":"...webp","1024":"...webp","1600":"...webp"}`) · `uploaded_by` FK SET NULL · timestamps · `deleted_at`
Index: `mime`, `created_at`.

### Inbound

**`contact_submissions`** — `id` · `name` · `email` · `phone` · `company` · `service_id` FK SET NULL · `budget_range` · `message` TEXT · `ip_hash` (hashed, never raw IP) · `user_agent` · `referrer` · `is_read` · `is_spam` · `replied_at` · `created_at` · `deleted_at`
Index: `(is_read, created_at)`.

**`newsletter_subscribers`** — `id` · `email` UNIQUE · `source` (`footer`,`blog`,`api`) · `confirm_token_hash` · `confirmed_at` · `unsubscribed_at` · `ip_hash` · `created_at`

### System

**`settings`** — `key` VARCHAR(100) PK · `value` LONGTEXT · `type` · `group_name` (`general`,`contact`,`social`,`seo`,`mail`,`analytics`) · `updated_at`
Key/value rather than one wide row, so adding a setting is an insert, not a migration.

**`activity_log`** — `id` · `user_id` FK SET NULL · `api_token_id` FK SET NULL (so agent-created posts are attributable to the token) · `action` (`post.published`) · `entity_type` · `entity_id` · `meta` JSON · `ip_hash` · `created_at`
Index: `(entity_type, entity_id)`, `created_at`.

**21 tables total** — the 19 required, plus `remember_tokens`, `rate_limits` and `migrations` as infrastructure.

---

## 4. Things in the brief that need adjusting

Flagged now rather than worked around silently.

1. **Cron granularity.** Hostinger shared allows a minimum cron interval of 5 minutes on Premium (1 minute on Business/Cloud). Scheduled publishing is therefore accurate to ~5 minutes, not to the second. Mitigation: any page request that encounters a `scheduled` post whose `published_at` has passed resolves it on the spot, so the public site is never stale even if cron is misconfigured or silently stops. Cron is the sweeper, not the only mechanism.

2. **Email deliverability.** PHP `mail()` is not used. More importantly: **the `From:` address cannot be a Gmail address.** Sending "from" `@gmail.com` via any other server fails Gmail's DMARC policy and lands in spam or gets rejected outright. Form notifications will send `From: noreply@<your-domain>` with `Reply-To:` set to the submitter, and *deliver to* whatever inbox you name. You'll need either a Hostinger/Titan mailbox on the domain or an SMTP provider (Brevo/SMTP2GO free tiers are fine at this volume), plus SPF and DKIM DNS records. `DEPLOY.md` will carry the exact records.

3. **Hero video vs. Lighthouse 90+ on mobile.** These are in tension. A self-hosted hero video is the single easiest way to miss the target — it competes with LCP for bandwidth and shared hosting has no CDN in front of it. The plan: a real poster image is the LCP element, and a short (~6 s, under 1.5 MB) muted looped WebM/MP4 attaches only after the LCP event, only above 1024px, only on `connection.saveData === false`, and never under `prefers-reduced-motion`. Mobile gets the poster plus a cheap CSS grain/gradient motion layer. This keeps the premium feel where it's visible and keeps the mobile score.

4. **HSTS.** Not enabled on first deploy. If SSL isn't fully working when a browser caches an HSTS header, that domain becomes unreachable over HTTP for the duration of `max-age`. It goes on after SSL is verified, starting at `max-age=300` and raised once stable.

5. **CSP with `unsafe-inline` is not a CSP.** Inline critical CSS and the small inline JSON-LD blocks will carry a per-request nonce. This is why no CDN-hosted script is used anywhere, admin included.

6. **`vendor/` committed.** Composer is not guaranteed on Hostinger shared. It's committed deliberately, not by accident, and `.gitignore` will say so.

7. **Domain spelling.** The brief says `subramanayam.in`. Given `subramanyammn.in` appears elsewhere in your work, please confirm the exact string — it's baked into canonical URLs, sitemap, OG tags, SPF/DKIM and the `.htaccess` www redirect, and is annoying to change after indexing.

### Optional, not built unless you ask

A `redirects` table (source → destination, 301) for retiring URLs later. Not needed at launch; ~40 lines to add whenever.

---

## 5. Phase 2 preview (what approval unlocks)

Git init, `composer.json` + vendor, the `app/Core` framework, all 21 migrations plus the runner, `seed.sql` with realistic placeholder content, auth (login, lockout, remember-me, password reset by emailed token), role middleware, and the admin shell — nav, layout, dashboard skeleton. No public site, no content modules yet.
