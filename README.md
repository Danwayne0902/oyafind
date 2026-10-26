# OyaFind

OyaFind helps people discover and assess legitimate, relevant opportunities across
Nigeria: jobs, gigs, internships, scholarships, training, grants, business support
and tenders.

OyaFind is **Nigeria-wide (36 states + FCT)**. It does not claim to list every
opportunity in Nigeria. Listings link to the original source, and a listing only
appears publicly after a staff reviewer approves it and a separate verifier records
the required checks.

## Stack

- Laravel 12 + PHP 8.3
- Vue 3 + Inertia + Vite
- MySQL in local development, SQLite in tests
- Instrument Sans as the product typeface

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
npm install
npm run build
```

Create the database configured in `.env`, then:

```bash
php artisan migrate
php artisan db:seed
```

`db:seed` loads categories, the reviewed Nigerian location catalogue
(37 state-level records and all Oyo LGAs), the first administrator account and the
source registry. Demo listings are available separately through
`php artisan db:seed --class=OpportunityDemoSeeder`; they stay non-public unless a
matching publication policy is configured.

### Feature policies fail closed

Registration, listing submission, the location catalogue, notifications and public
discovery are owner-approved switches. An unset switch means the feature is off.
Public discovery additionally needs a complete reviewer/verifier policy before any
listing can appear. See the commented block in `.env.example`.

## Opportunity ingestion

Feeds only fill a **review queue**. Nothing auto-publishes.

| Source | Adapter | Default category | Notes |
|---|---|---|---|
| Jobzilla | RSS | Jobs | Nigerian jobs, daily |
| Opportunities for Africans | RSS | Scholarships | Scholarships, internships, grants |
| Opportunity Desk | RSS | Scholarships | Global opportunities open to Africans |
| Funds for NGOs | RSS | Grants | Grants and funding; Cloudflare-protected, may fail intermittently |
| World Bank procurement | JSON API | Tenders | Nigeria-only, open notices only; contract awards excluded |

Register or update them with:

```bash
php artisan db:seed --class=SourceSeeder
```

Run ingestion:

```bash
php artisan opportunities:ingest --dry-run          # fetch and normalise, save nothing
php artisan opportunities:ingest                    # stage new candidates
php artisan opportunities:ingest --source=jobzilla  # one source
php artisan opportunities:expire                    # close pending items past their deadline
```

The scheduler in `routes/console.php` runs ingestion every 30 minutes and expiry
hourly. Ingestion failures are isolated per source and recorded on the `sources`
row; one broken feed never stops the others.

What the normaliser does before staging:

- keeps a short excerpt and the canonical source link, never the full article;
- removes email addresses, phone numbers and URLs found in feed content;
- rejects off-domain links, roundups, advice posts, templates and expired items;
- maps a category from feed categories or the title, then the source default;
- matches Nigerian states and major cities against the reviewed location catalogue;
- deduplicates by canonical URL and by a title/organisation key.

## Review and publication

Publishing is deliberately two separate steps performed by two different
allowlisted accounts:

```bash
# 1. Staff reviewer approves or rejects candidates.
php artisan opportunities:review --reviewer=5

# 2. A different verifier runs the required checks and publishes.
php artisan opportunities:verify --verifier=24
```

Inspect the queue without changing anything:

```bash
php artisan opportunities:pending
php artisan opportunities:pending --status=reviewed
php artisan opportunities:pending --source=jobzilla --limit=20
```

During review, `edit` lets the reviewer correct the category. Approval only moves
the item to `reviewed`; it is still not public. During verification, `publish`
writes append-only verification records for each required check type
(`source_live`, `details_match`), an audit event for the staff approval, and then
marks the item `approved`. The public feed only returns listings that satisfy all
of those records and the configured policy.

Useful commands:

```bash
php artisan opportunities:pending --status=filtered
php artisan schedule:list
```

## Deployment

`deploy/` holds a single-VM production stack: MySQL, PHP-FPM, the scheduler and
Caddy with automatic HTTPS, all in Docker. It targets an Oracle Cloud Always
Free instance (Ubuntu 24.04, arm64) but runs on any 2 GB Ubuntu VPS.

```bash
sudo APP_DOMAIN=oyafind.com.ng ACME_EMAIL=you@example.com \
     OYAFIND_REPO=git@github.com:Danwayne0902/oyafind.git \
     ./deploy/provision-oracle.sh
```

The full runbook — domain and DNS, Oracle's two firewall layers, staff accounts,
mail, backups and troubleshooting — is in [`deploy/README.md`](deploy/README.md).
Production sets its own reviewer and verifier ids; the local demonstration policy
is not reused.

## Tests

```bash
php artisan test
npm run test:js
npm run build
```

The test suite covers feed filtering, contact scrubbing, deduplication, World Bank
mapping, the two-step publication policy, distinct reviewer/verifier enforcement,
deadline expiry and public-feed visibility.

## Data and attribution

- Listings store a short excerpt and link to the original page; they do not
  republish articles.
- Contact details from third-party pages are dropped rather than stored.
- Content is attributed to its source; source names and terms links live in the
  `sources` table.
- Sources are enabled only where their `robots.txt` and terms permit automated
  access to a public feed or API; OyaFind does not bypass Cloudflare or other
  access controls. Sources that block automated access are disabled instead of
  forced.

## Known limitations

- Tender coverage outside World Bank procurement is thin; Nigerian procurement
  portals do not expose a stable public feed.
- Gig coverage is thin; most Nigerian gig boards have no machine-readable feed.
- A staff review web UI does not exist yet; review and verification are CLI-only.
- Deadlines are parsed as UTC. A date-only source deadline closes at the end of
  that day in UTC; reviewers should confirm the exact time and timezone on the
  source page before publishing.
