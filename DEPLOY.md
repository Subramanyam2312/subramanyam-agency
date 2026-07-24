# Deploying to Hostinger (shared hosting)

For **subramanyammn.in** on a Hostinger shared plan (Premium or Business).

Nothing here needs SSH. Everything can be done through hPanel's File Manager, but
SSH makes several steps faster and is noted where it helps.

Budget about an hour for the first deploy.

---

## 0. Before you start

Have ready:

- hPanel login
- The domain pointed at Hostinger (Websites → your site → DNS)
- A local copy of the project with `vendor/` present and `public/assets/css/app.css` built

Run the pre-flight checks locally:

```bash
php scripts/security-audit.php
./resources/build-css.sh
```

Both must pass before you upload anything. The audit exits non-zero on failure, so
it is safe to wire into a deploy script later.

---

## 1. Create the database

hPanel → **Databases → Management**.

1. Create a database. Hostinger prefixes it, so `agency` becomes something like
   `u123456789_agency`.
2. Create a user and give it a long random password. Save it somewhere real — it
   is shown once.
3. Assign the user to the database with **All privileges**.

Note down all four values. They go into `.env` in step 4:

| | Example |
|---|---|
| `DB_HOST` | `localhost` |
| `DB_DATABASE` | `u123456789_agency` |
| `DB_USERNAME` | `u123456789_agency` |
| `DB_PASSWORD` | the password you just set |

> `DB_HOST` is **`localhost`**, not `127.0.0.1`. On Hostinger the MySQL socket is
> local; `127.0.0.1` forces a TCP connection that is sometimes refused.

---

## 2. Set the PHP version and extensions

hPanel → **Advanced → PHP Configuration**.

- **PHP version:** 8.2 or 8.3. Do not use 8.0 or below — the code uses enums,
  readonly promotion and `never` return types.
- **Extensions**, all required: `pdo_mysql`, `mbstring`, `gd`, `fileinfo`,
  `openssl`, `curl`, `json`.

`gd` powers WebP generation, `fileinfo` powers upload validation, and `curl` is
what lets the API fetch a remote image. Without `curl` everything else still works;
only `POST /api/v1/media` with a `url` is affected.

In the **PHP options** tab, confirm:

```
upload_max_filesize = 16M
post_max_size       = 20M
memory_limit        = 256M
```

`upload_max_filesize` must exceed the app's own 8 MB limit, or large uploads fail
in the web server before PHP ever reports a useful error.

---

## 3. Upload the files

This is the step people get wrong, so read it twice.

**The repository layout is not the deployed layout.** `app/`, `storage/`, `vendor/`,
`database/` and `scripts/` must sit **above** the web root, and only the *contents*
of `public/` go inside it:

```
~/domains/subramanyammn.in/
├── app/
├── database/
├── scripts/
├── storage/
├── vendor/
├── .env                 ← created in step 4
└── public_html/         ← the web root
    ├── index.php
    ├── .htaccess
    ├── assets/
    ├── uploads/
    └── robots.txt, sitemap.xml (generated later)
```

`public/index.php` probes one level up for `app/`, then two, so the same code runs
locally and here with no edit.

### Doing it in File Manager

1. Zip the project locally **excluding** `.git`, `.env` and `node_modules`.
2. hPanel → **Files → File Manager**, go to `~/domains/subramanyammn.in/`.
3. Upload the zip and extract it there.
4. Move the *contents* of the extracted `public/` into `public_html/`, then delete
   the now-empty `public/`.
5. Confirm `public_html/index.php` exists and `public_html/app` does **not**.

### Or over SSH (Business plans and up)

```bash
cd ~/domains/subramanyammn.in
unzip -q agency.zip
rsync -a public/ public_html/
rm -rf public public/
```

### Permissions

```
directories  755
files        644
storage/     775   (and everything inside it)
public_html/uploads/  775
```

`storage/` and `uploads/` must be writable by PHP. Everything else must not be.

---

## 4. Create `.env`

In `~/domains/subramanyammn.in/` (**not** in `public_html`), create `.env` from
`.env.example`.

Generate the key first. On SSH:

```bash
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

No SSH? Generate it locally and paste it — it is just 64 hex characters.

```ini
APP_NAME="SUBRAMANYAM"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://subramanyammn.in
APP_KEY=<the 64 characters you just generated>
APP_TIMEZONE=Asia/Kolkata

DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=u123456789_agency
DB_USERNAME=u123456789_agency
DB_PASSWORD=<your database password>

SESSION_NAME=agency_session

MAIL_DRIVER=smtp
MAIL_HOST=<see step 7>
MAIL_PORT=587
MAIL_USERNAME=<see step 7>
MAIL_PASSWORD=<see step 7>
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@subramanyammn.in
MAIL_FROM_NAME="SUBRAMANYAM"
MAIL_TO_ADDRESS=you@example.com
MAIL_TO_NAME="Subramanyam"

SECURITY_HSTS=false
SECURITY_HSTS_MAX_AGE=300
```

**`APP_DEBUG` must be `false`.** With it on, a stack trace on any error hands a
visitor your absolute filesystem paths and database name.

Leave `SECURITY_HSTS=false` for now. Step 6 turns it on.

---

## 5. Create the schema

**With SSH:**

```bash
cd ~/domains/subramanyammn.in
php database/migrate.php --seed
php scripts/create-admin.php
```

**Without SSH**, use hPanel → **Databases → phpMyAdmin**:

1. Select your database → **Import**.
2. Import each file in `database/migrations/` **in filename order**
   (`0001_…` first). Order matters — the foreign keys depend on it.
3. Import `database/seed.sql` for the placeholder content, or skip it to start empty.
4. Create your admin account with the temporary web route in the next section.

### Creating the first admin without SSH

`scripts/create-admin.php` is CLI-only by design. Without SSH, use hPanel's
**Cron Jobs** to run it once:

```
php /home/uXXXXXXXX/domains/subramanyammn.in/scripts/create-admin.php --name="Subramanyam" --email="you@example.com" --role=admin --password="<a long password>"
```

Set it to run once (pick a time a couple of minutes out), let it fire, then
**delete the cron job** — the command line contains your password and cron logs it.
Change the password after your first sign-in.

---

## 6. SSL and HTTPS

hPanel → **Security → SSL**.

1. Install the free Let's Encrypt certificate for the domain and `www`.
2. Wait for it to say **Active** — usually a few minutes.
3. Turn on **Force HTTPS**.
4. Load `https://subramanyammn.in` and confirm the padlock, then confirm
   `http://subramanyammn.in` redirects to it.

**Only once all of that works**, enable HSTS in `.env`:

```ini
SECURITY_HSTS=true
SECURITY_HSTS_MAX_AGE=300
```

Leave it at 300 for a week. If nothing breaks, raise it to `31536000`.

> Enable HSTS before SSL works and browsers will refuse to load the site over
> HTTP for the full `max-age`, with no way to undo it from the server. That is
> why it starts at five minutes.

The `.htaccess` in `public_html` already forces HTTPS and redirects `www` to
non-www. It checks `X-Forwarded-Proto` as well as `HTTPS`, because Hostinger
terminates TLS at a proxy and checking `%{HTTPS}` alone causes a redirect loop.

**If you prefer `www.subramanyammn.in` as canonical**, edit that block in
`public_html/.htaccess` and change `APP_URL` to match. Both must agree or you get
a redirect loop.

---

## 7. Email

**Do not skip this.** `MAIL_FROM_ADDRESS` must be on your own domain. Sending
"from" a gmail.com address through Hostinger's server fails Gmail's DMARC policy,
and your contact form notifications will silently vanish.

### Option A — Hostinger email (simplest)

1. hPanel → **Emails → Email Accounts** → create `noreply@subramanyammn.in`.
2. Put those credentials in `.env`:

```ini
MAIL_HOST=smtp.hostinger.com
MAIL_PORT=587
MAIL_USERNAME=noreply@subramanyammn.in
MAIL_PASSWORD=<that mailbox's password>
MAIL_ENCRYPTION=tls
```

SPF and DKIM are added automatically when you use Hostinger email on a
Hostinger-hosted domain. Verify under **Emails → DNS settings**.

### Option B — external SMTP (better deliverability)

Brevo, SMTP2GO and Resend all have free tiers that cover this volume. Use their
host, port 587, and their credentials. You must then add their SPF and DKIM
records to your DNS yourself — follow their setup guide exactly.

### Test it

Trigger a real password reset from `/admin/forgot-password` and confirm the email
arrives. If it does not, check `storage/logs/php-error.log` — `Mailer` logs the
SMTP failure there and deliberately does not surface it to the visitor.

---

## 8. Cron jobs

hPanel → **Advanced → Cron Jobs**. Replace `uXXXXXXXX` with your account name.

**Scheduled publishing** — every 5 minutes:

```
*/5 * * * *  php /home/uXXXXXXXX/domains/subramanyammn.in/scripts/publish-scheduled.php
```

**Sitemap rebuild** — nightly at 03:15:

```
15 3 * * *  php /home/uXXXXXXXX/domains/subramanyammn.in/scripts/publish-scheduled.php --sitemap
```

Five minutes is Hostinger's floor on Premium (Business allows one minute). That
is why the blog also resolves any overdue scheduled post when a page is read —
the cron is a sweeper, not the mechanism, and the site stays correct even if cron
silently stops. Which, on shared hosting, it eventually does.

The sitemap is also regenerated on every publish, so the nightly run is only a
safety net for a write that failed quietly.

---

## 9. Post-deploy checklist

Work through this in order.

- [ ] `https://subramanyammn.in` loads over HTTPS with a valid certificate
- [ ] `http://` redirects to `https://`, and `www.` redirects to non-www
- [ ] **`https://subramanyammn.in/.env` returns 403 or 404 — never file contents**
- [ ] `https://subramanyammn.in/app/` returns 403 or 404
- [ ] `https://subramanyammn.in/storage/logs/` returns 403 or 404
- [ ] Sign in at `/admin`, then **change the password you set in step 5**
- [ ] Settings → General: site name, tagline, contact details, social links
- [ ] Page copy: check the home and about text reads the way you want
- [ ] Upload a real image in Media and confirm WebP variants generate
- [ ] Submit the contact form and confirm the email arrives
- [ ] `/sitemap.xml` and `/robots.txt` both load
- [ ] `/feed.xml` loads and validates
- [ ] Publish a test post, confirm it appears at `/blog`, then delete it
- [ ] Tools → API tokens: create your first token and save it somewhere safe
- [ ] Verify the domain in Google Search Console, paste the token into
      Settings → SEO, then submit `https://subramanyammn.in/sitemap.xml`
- [ ] Enable HSTS (step 6) once everything above passes
- [ ] Run Lighthouse on mobile against the live domain and record the score

### The `.env` check is not optional

If `https://subramanyammn.in/.env` shows you the file, **stop and fix the layout
immediately** — your database password and `APP_KEY` are public. It means `.env`
ended up inside `public_html` instead of one level above it. Move it, then rotate
the database password and `APP_KEY`.

---

## 10. Deploying updates

1. Build CSS locally: `./resources/build-css.sh`
2. Run `php scripts/security-audit.php` — it must pass
3. Upload the changed files (skip `.env`, `storage/`, `public_html/uploads/`)
4. Run any new migrations: `php database/migrate.php`, or import the new
   `.sql` files through phpMyAdmin in filename order
5. Load the site and check `storage/logs/php-error.log` is quiet

`vendor/` is committed, so there is no Composer step on the server.

Assets are fingerprinted with `?v=<mtime>`, so a changed file gets a new URL and
browsers pick it up immediately despite the one-year cache header.

---

## Troubleshooting

**500 error, blank page**
Read `storage/logs/php-error.log`. If it is empty, PHP could not write there —
check that `storage/` is `775`. Set `APP_DEBUG=true` briefly to see the error on
screen, then **set it back to `false`**.

**"Application directory not found"**
`index.php` cannot locate `app/`. The folder layout in step 3 is wrong — `app/`
must be a sibling of `public_html`, not inside it.

**Database connection failed**
`DB_HOST` should be `localhost`. Confirm the user is assigned to the database
with all privileges, and that the database name includes the `uXXXXXXXX_` prefix.

**Redirect loop**
`APP_URL` disagrees with the canonical host in `.htaccess` — one says `www`, the
other does not. Make them match.

**Images upload but do not display**
`public_html/uploads/` is not writable, or its `.htaccess` was lost in the upload.
It must exist and must contain the `RemoveHandler` rules — those are what stop an
uploaded file from ever executing.

**Scheduled posts never publish**
Check the cron job path is absolute and the PHP binary is right. The site will
still publish them lazily on read, so this shows up as "late", not "never".
