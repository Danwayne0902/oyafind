# OyaFind — Product

**Tagline:** Real opportunities. Closer to you.
**Platform:** Web, mobile-first, installable PWA.
**Market:** Nigeria — all 36 states and the Federal Capital Territory.
**Status:** Approved PRD baseline. This file records durable product truth only. It does not define a visual world; that lives in `DESIGN.md`.

## What it is

OyaFind is a local opportunity board. It helps people find jobs, gigs, internships, scholarships, training, grants, business support and tenders that are relevant to where they live, and to judge whether a listing is worth their time before they pursue it.

OyaFind covers Nigeria's 36 states and the Federal Capital Territory through a catalogue of reviewed states and local government areas. It does not claim to list every opportunity in the country, and it must not describe itself as doing so.

## Who it is for

- **Students** — scholarships, internships, training and entry-level work.
- **Jobseekers and workers** — employment, part-time work and local gigs.
- **Graduates** — entry-level roles, internships, training and grants.
- **Skilled workers and apprentices** — trade work, gigs and vocational training.
- **Young entrepreneurs and traders** — grants, business support and tenders.
- **Organisations** — a credible route to relevant local candidates and participants.
- **OyaFind staff** — review listings, record checks, handle reports and keep listings current.

The service is open to everyone. Categories, search and location filters support different needs; there are no eligibility gates.

## The job it does

Someone hears about an opportunity through a WhatsApp group or a friend, and cannot tell whether it is current, real, or meant for them. OyaFind gives them one place to:

1. See what is open near them, without creating an account.
2. Understand the organisation, location, deadline, eligibility and how to apply.
3. See when the source and details were last checked — and what that check does and does not mean.
4. Reach the provider's own application channel directly.
5. Keep a private list of saved opportunities and their own application progress.

## Product principles

1. **Trust is specific, not decorative.** Publication status and verification status are different things, and the interface must never merge them into a vague "verified" glow.
2. **No invented certainty.** OyaFind does not guarantee safety, selection, payment or outcomes, and must not claim checks it has not performed.
3. **Browse first.** Public discovery works without an account. Accounts exist for saving and private tracking.
4. **Local relevance, national reach.** Listings are organised by reviewed state and LGA areas across all 36 states and the FCT. Local relevance is the unit of discovery, not a single-city limit.
5. **Low data, low friction.** Text-first, bounded lists, no autoplay media, no GPS, no maps, no third-party locator calls.
6. **Mobile is the product.** The primary experience is a phone on an intermittent connection, not a desktop marketing page.
7. **The feed is the front door.** The first screen is for finding something, not for reading a pitch.

## Trust model

- **Publication status** answers: is OyaFind displaying this listing now?
- **Verification status** answers: what was the outcome of OyaFind's source/detail check?
- Only listings whose source and details have been checked may be published publicly. Staff review alone is not verification.
- "Verified" must never be synthesised from submission, staff approval, missing information or an assumed check. The detailed checklist, evidence standard, reviewer authorisation and recheck mechanism remain open policy decisions.
- A check is not an endorsement, a guarantee of provider safety, or a guarantee of selection or payment.
- Listings with a known deadline expire when it passes. Listings without a deadline are rechecked every 30 days and hidden when that recheck is overdue.
- Public users must be able to report concerns without an account. Reporting is a required product capability but is **not implemented in this build**; the UI must not pretend otherwise.

## Accounts and private data

- Registration uses an email **or** phone number plus a password.
- The selected contact must be verified before the account can save opportunities.
- Contributor and employer submission access requires staff approval. Elevated roles are never available through public registration.
- Saved opportunities and tracker records are private to their owner. Tracker statuses are user-reported: `interested`, `preparing`, `applied`, `interview`, `successful`, `unsuccessful`, `user-reported`.
- OyaFind does not submit applications, receive employer confirmations, or imply that a tracked application was submitted through OyaFind.
- Notifications are in-app only. No push.

## Scope

**In:** public paginated feed; search; category and coarse-area filters; listing detail and sharing; accounts; save/unsave; private tracker; staff-reviewed listing publication; verified source/detail records; expiry; PWA shell and conservative offline states.

**Out:** payments, wallets, sponsored placements, messaging, comments, social features, AI matching, native apps, GPS, exact addresses, geocoding, interactive maps.

## Constraints

- Laravel 12 / PHP 8.2, Vue 3, MySQL, session-cookie authentication with CSRF.
- Location discovery uses OyaFind's own catalogue of human-reviewed coarse areas. Never request device GPS or collect an exact address.
- Analytics must not collect unnecessary personal information.
- Public API responses exclude internal notes, submitter contacts and private evidence.
- Accessibility target: WCAG 2.2 AA for the core routes.
- Performance target (proposed, not validated): p75 LCP ≤ 2.5s and an agreed initial transfer budget on a representative Android device and mobile network.

## Open decisions

Verification checklist and evidence standard; recheck operations beyond the 30-day rule; report reasons, triage and response commitments; account deletion and retention; privacy/legal policy; whether organisations need a separate entity; numeric launch success thresholds; ongoing ownership of the national location taxonomy and its approver; currency display convention; hosting and operational ownership.

## Assumptions

- The PWA form is retained from the brief and has not been validated with users.
- Demand, conversion and willingness to pay are hypotheses, not research findings.
- The 30-day measurement window is a planning assumption, not a launch date.
