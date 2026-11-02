# OyaFind search visibility playbook

Ranking is not a switch that can be flipped in code. It is the product of
crawlable, useful pages, a technically healthy site, and authority earned over
time. This document separates what the codebase already does from what only the
owner can do.

Nobody can promise a top-3 position. Any vendor who does is guessing.

## What the application already does

- **Server-rendered metadata.** Every public route returns a real `<title>`,
  description, canonical URL, Open Graph/Twitter tags and a crawlable fallback
  inside `#app`, before the Vue bundle runs.
- **Structured data.** `Organization`, `WebSite` with `SearchAction`,
  `BreadcrumbList`, `CollectionPage`/`ItemList` for category pages, and
  `JobPosting` for jobs. Job locations carry the real country code; remote jobs
  are marked `TELECOMMUTE` instead of being given a fake address.
- **Honest sitemap.** `/sitemap.xml` is generated from the same evidence-backed
  query as the public feed, so withdrawn, expired or unverified listings never
  appear. Category + area combinations are included only when a live listing
  exists.
- **Feed.** `/feed.xml` publishes the newest 50 live listings for readers and
  aggregators, and is advertised in every page head.
- **Internal links.** Category pages link to the areas that actually hold
  listings ("Jobs in Lagos"), and listings link to their category and area.
  These are real `<a>` elements in the server-rendered HTML, not only JavaScript
  navigation.
- **Thin pages stay out.** Search results, paginated pages and private screens
  are `noindex`, so crawl budget goes to pages worth ranking.
- **Robots.** `public/robots.txt` allows the public site and disallows admin,
  API, auth and account screens, and points at the sitemap.
- **Performance basics.** Hashed, compressed assets with immutable caching,
  OPcache enabled, HTTP/2/3 via Caddy, fonts self-hosted.

## What only the owner can do

1. **Google Search Console** — add `oyafind.com.ng` as a property, verify it,
   submit `https://oyafind.com.ng/sitemap.xml`, then use URL Inspection to
   request indexing for the home page and the eight category pages.
   To verify by meta tag, set `GOOGLE_SITE_VERIFICATION` in the production
   `.env` and rebuild; the tag is emitted automatically.
2. **Bing Webmaster Tools** — import from Search Console and submit the same
   sitemap. Bing also feeds some AI answer surfaces.
3. **Analytics** — add a privacy-respecting analytics tool (for example
   Plausible or Umami) so you can see which queries and pages earn impressions.
   Do not add a heavy tag manager just for this.
4. **Content cadence** — publish genuinely new listings every week. Freshness
   is a real ranking input for job-seeker queries, and the feed plus sitemap
   `lastmod` already reflect updates.
5. **Earn links** — the highest-leverage work left. Realistic sources:
   Nigerian university career offices, NYSC forums, tech communities, Slack and
   WhatsApp groups with public pages, local news coverage of a data story
   ("where the jobs actually are"), and partnerships with the organisations
   whose listings are indexed. A handful of relevant links beats hundreds of
   directory links.
6. **Social profiles** — create profiles you actually maintain, then list their
   URLs in `OYAFIND_SEO_SAME_AS` (comma-separated). This strengthens the
   publisher identity in `Organization` structured data. Never point it at an
   account you do not control.
7. **Reviews and mentions** — a Google Business Profile is not appropriate for
   a listing aggregator, but mentions on reputable Nigerian sites are.

## Rules that protect the site

- Never invent verification, coverage or "official partner" claims. Google's
  spam policies target exactly that, and one manual action costs more than a
  year of optimisation.
- Do not mass-generate location pages for areas with no listings. The current
  sitemap only includes combinations that hold a live listing, which is why it
  stays trustworthy.
- Keep listing summaries original. Republishing full source text duplicates
  other sites and gives Google no reason to rank OyaFind.
- Keep every public listing's location reviewed. Thin, location-less pages
  dilute the whole site's quality signals.

## How to check the work

```bash
curl -s https://oyafind.com.ng/sitemap.xml | head -20
curl -s https://oyafind.com.ng/feed.xml | head -20
curl -s -o /dev/null -w '%{http_code}\n' https://oyafind.com.ng/.env
```

Structured data can be checked with Google's Rich Results Test and the Schema
Markup Validator. The JobPosting schema is the one that can surface listings in
the Google Jobs experience; it requires the page to stay live and the
`validThrough` date to remain in the future, which the expiry job already
enforces by withdrawing stale listings.
