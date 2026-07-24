# REST API v1

Token-authenticated JSON API for publishing to this site from an external client.

Base URL: `https://subramanyammn.in/api/v1`

---

## Getting a token

Admin portal → **API tokens** → name it, tick the abilities, create.

The token is displayed **once**. Only its SHA-256 is stored, so it cannot be
recovered — if it is lost, revoke it and issue a new one.

Tokens look like `sub_` followed by 64 hex characters. The prefix makes them
recognisable in logs and to secret scanners.

### Abilities

| Ability | Grants |
|---|---|
| `read` | `GET` on posts, media, categories, tags |
| `write` | `POST` and `PATCH` on posts and media |

Deliberately coarse. Fine-grained scopes that nobody can reason about end up being
granted wholesale, which is worse than two honest ones.

---

## Authentication

Send the token as a bearer credential on every request:

```bash
curl https://subramanyammn.in/api/v1/me \
  -H "Authorization: Bearer sub_YOUR_TOKEN_HERE"
```

The API is exempt from CSRF because it uses a bearer token rather than a cookie —
there is no ambient authority for a cross-site request to borrow.

**Store the token in a file or environment variable, never in a committed script.**

```bash
export AGENCY_TOKEN="$(cat ~/.agency-token)"
```

---

## Response shapes

Exactly two, always. A client never has to guess whether an error arrived as a
string, an object, or an HTML page.

**Success**

```json
{ "data": { ... }, "meta": { ... } }
```

`meta` appears only on paginated collections.

**Failure**

```json
{
  "error": {
    "code": "validation_failed",
    "message": "The submitted data was not valid.",
    "details": { "title": "Title is required." }
  }
}
```

### Status codes

| Code | Meaning |
|---|---|
| 200 | OK |
| 201 | Created — `Location` header points at the new resource |
| 401 | Missing, invalid, revoked or expired token |
| 403 | Token authenticated but lacks the required ability |
| 404 | No such resource |
| 422 | Validation failed — see `error.details` |
| 429 | Rate limited — see `Retry-After` |

### Error codes

`unauthenticated` · `invalid_token` · `forbidden` · `not_found` ·
`validation_failed` · `rate_limited`

---

## Rate limiting

**120 requests per minute per token.** Keyed on the token rather than the IP, so an
agent behind a shared address is not throttled by someone else's traffic, and
rotating IPs does not reset the count.

Every response carries:

```
X-RateLimit-Limit: 120
X-RateLimit-Remaining: 107
```

A 429 additionally carries `Retry-After` in seconds.

---

## Endpoints

### `GET /me`

Verifies a token and reports what it can do. Call this first — it is cheaper than
discovering a permissions problem halfway through a publish.

```bash
curl https://subramanyammn.in/api/v1/me \
  -H "Authorization: Bearer $AGENCY_TOKEN"
```

```json
{
  "data": {
    "token": { "id": 1, "name": "Publishing agent", "prefix": "bee2bfc7",
               "abilities": ["read", "write"], "expires_at": null,
               "last_used_at": "2026-07-24 19:02:11" },
    "owner": { "name": "Subramanyam", "email": "…", "role": "admin" },
    "site":  { "name": "SUBRAMANYAM", "url": "https://subramanyammn.in" }
  }
}
```

---

### `GET /posts`

Requires `read`.

| Query | Default | Notes |
|---|---|---|
| `page` | 1 | |
| `per_page` | 20 | Max 50 |
| `search` | — | Matches title, slug, excerpt |
| `status` | — | `draft`, `scheduled` or `published` |
| `category_id` | — | |

```bash
curl "https://subramanyammn.in/api/v1/posts?status=published&per_page=5" \
  -H "Authorization: Bearer $AGENCY_TOKEN"
```

```json
{
  "data": [ { "id": 11, "title": "…", "slug": "…", "status": "published", … } ],
  "meta": { "page": 1, "per_page": 5, "total": 4, "last_page": 1 }
}
```

---

### `GET /posts/{id}`

Requires `read`. Returns a single post in the same shape.

---

### `POST /posts`

Requires `write`. Returns **201** with a `Location` header.

| Field | Required | Notes |
|---|---|---|
| `title` | yes | Max 200 |
| `content` | yes | HTML — sanitised on save, see below |
| `slug` | no | Generated from the title; a number is appended if taken |
| `excerpt` | no | Max 500 |
| `status` | no | Default `draft` |
| `published_at` | no | Any format `strtotime` understands |
| `category` | no | Category **slug** — usually easier than an id |
| `category_id` | no | Alternative to `category` |
| `tags` | no | Array of names, or a comma-separated string. Unknown tags are created |
| `featured_media_id` | no | Id from `POST /media` |
| `featured_image` | no | `{ "url": … }` or `{ "base64": …, "filename": … }` |
| `is_featured` | no | Boolean |
| `meta_title`, `meta_description`, `canonical_url`, `noindex` | no | SEO fields |

```bash
curl -X POST https://subramanyammn.in/api/v1/posts \
  -H "Authorization: Bearer $AGENCY_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Published straight from the API",
    "content": "<h2>It works</h2><p>Created by an external client.</p>",
    "excerpt": "A post created over the REST API.",
    "category": "seo",
    "tags": ["API", "Automation"],
    "status": "published",
    "meta_title": "API created post"
  }'
```

#### Scheduling

`status` and `published_at` resolve exactly as they do in the CMS, so the two can
never drift apart:

| You send | Result |
|---|---|
| `published`, no date | Published now |
| `scheduled`, future date | Stays scheduled until then |
| `scheduled`, past date | Published immediately |
| `scheduled`, **no** date | Falls back to `draft` — never silently published |

Scheduled posts go live via cron (5-minute granularity on Hostinger shared) and are
also resolved lazily on the first public read, so the site is never stale even if
cron stops.

#### Content sanitising

`content` is sanitised **on save** against a whitelist, so what is stored is already
safe for the API, feeds and exports rather than relying on every consumer to clean
it. Dropped without comment: `<script>`, `on*` handlers, `javascript:` URLs, and
iframes outside the embed whitelist (YouTube, Vimeo, Google Maps).

Permitted: `p br strong em u s h2 h3 h4 ul ol li blockquote pre code hr a img
figure figcaption table` and their usual attributes.

Send the content you want; expect the safe subset back.

---

### `PATCH /posts/{id}`

Requires `write`. **True PATCH semantics** — only the fields you send are touched,
everything else keeps its stored value. Omitting a field never nulls it.

```bash
curl -X PATCH https://subramanyammn.in/api/v1/posts/11 \
  -H "Authorization: Bearer $AGENCY_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"status": "draft", "excerpt": "Edited via PATCH."}'
```

To unpublish, patch `status` to `draft`.

> **There is no `DELETE`.** An automated client that can create and update can
> unpublish by patching status; a loop in someone's script should not be able to
> destroy content. Deletion stays a deliberate human action in the CMS.

---

### `POST /media`

Requires `write`. Returns **201**. Three accepted forms.

**Multipart upload** — preferred:

```bash
curl -X POST https://subramanyammn.in/api/v1/media \
  -H "Authorization: Bearer $AGENCY_TOKEN" \
  -F "file=@hero.jpg"
```

**Base64** — also fine, and the right choice for a generated image:

```bash
curl -X POST https://subramanyammn.in/api/v1/media \
  -H "Authorization: Bearer $AGENCY_TOKEN" \
  -H "Content-Type: application/json" \
  -d "{\"base64\": \"$(base64 -i hero.jpg)\", \"filename\": \"hero.jpg\"}"
```

**URL** — works, but is the least preferred:

```bash
curl -X POST https://subramanyammn.in/api/v1/media \
  -H "Authorization: Bearer $AGENCY_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"url": "https://example.com/hero.jpg"}'
```

A URL means this server makes an outbound request that *you* chose, which is
server-side request forgery if unguarded. So: HTTPS only, every resolved address
checked against private and reserved ranges before the request is made, redirects
refused (a 302 to `127.0.0.1` is the standard bypass), a hard size cap and a short
timeout. Requests to loopback, RFC1918 and link-local addresses — including
`169.254.169.254` — are refused.

That path is guarded, but the safest fetch is the one that never happens. Use
multipart or base64 where you can.

**Response**

```json
{
  "data": {
    "id": 7,
    "url": "https://subramanyammn.in/uploads/2026/07/aaa832dd….jpg",
    "mime": "image/jpeg", "width": 1200, "height": 800, "size": 84213,
    "alt_text": null,
    "variants": { "320": "uploads/…-320.webp", "640": "…", "1024": "…" }
  }
}
```

WebP renditions are generated automatically at 320/640/1024/1600, skipping any
width larger than the source rather than upscaling.

Accepted: JPG, PNG, WebP, GIF, SVG, up to 8 MB. Validated by content sniffing, not
by file extension. SVGs are parsed and stripped of script, event handlers and
external references before being stored.

---

### `PATCH /media/{id}`

Requires `write`. Sets `alt_text` and `caption`.

```bash
curl -X PATCH https://subramanyammn.in/api/v1/media/7 \
  -H "Authorization: Bearer $AGENCY_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"alt_text": "Dashboard showing a 214% increase in enquiries"}'
```

Please set alt text. It is an accessibility requirement, and an image published
without it is a defect the CMS will flag.

---

### `GET /categories`

Requires `read`. Use it to discover valid slugs for `POST /posts`.

```bash
curl https://subramanyammn.in/api/v1/categories \
  -H "Authorization: Bearer $AGENCY_TOKEN"
```

```json
{ "data": [ { "id": 1, "name": "SEO", "slug": "seo",
              "description": "…", "post_count": 2 } ] }
```

---

### `GET /tags`

Requires `read`. Same shape, with `post_count`.

Creating a post with unknown tag names creates those tags, so this is for
discovery, not a prerequisite.

---

## A complete publish

Upload the image, then create the post referencing it:

```bash
TOKEN="$(cat ~/.agency-token)"
BASE="https://subramanyammn.in/api/v1"

MEDIA_ID=$(curl -sS -X POST "$BASE/media" \
  -H "Authorization: Bearer $TOKEN" \
  -F "file=@hero.jpg" | php -r 'echo json_decode(stream_get_contents(STDIN),true)["data"]["id"];')

curl -sS -X POST "$BASE/media/$MEDIA_ID" -X PATCH \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"alt_text": "Describe the image here"}'

curl -sS -X POST "$BASE/posts" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d "{
    \"title\": \"Your headline\",
    \"content\": \"<p>Body copy.</p>\",
    \"excerpt\": \"One-line summary.\",
    \"category\": \"seo\",
    \"tags\": [\"SEO\"],
    \"status\": \"published\",
    \"featured_media_id\": $MEDIA_ID
  }"
```

Or skip the two-step entirely by inlining the image:

```json
{
  "title": "Your headline",
  "content": "<p>Body copy.</p>",
  "status": "published",
  "featured_image": { "base64": "…", "filename": "hero.jpg" }
}
```

---

## Notes for automated clients

- **Check `GET /me` first.** It confirms the token, its abilities and the site it
  points at, and costs one request.
- **Every write is audited.** The activity log records the acting user *and* the
  specific token id, so an agent's output is traceable to the credential that made
  it, not just to whoever owns that credential.
- **Publishing is immediate and public.** There is no staging step. Create as
  `draft` first if a human should look before it goes live.
- **Slugs are stable.** Changing `title` on an existing post does not move its URL;
  send `slug` explicitly if a move is intended.
- **Revocation is instant.** A revoked token starts returning 401 on its very next
  request; nothing is cached.
