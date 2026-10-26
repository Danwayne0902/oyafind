# OyaFind — Design

**Direction:** *The Nigerian opportunity ledger.* A civic noticeboard for real openings: warm paper, precise rules, plain language, and evidence you can point at. Not a startup landing page, not a job-board clone.

**Surface mode:** Operate (browse, assess, save, track). Ambition shows as restraint, density and finish — not spectacle.

**Brand commitments:** the existing mark and the navy are fixed. Lime stays, but as a highlighter — a mark, an underline, a focus accent — never the main call to action. The palette gains warm paper and a single trust green.

## Why this direction

The product's promise is that someone checked before publishing. That promise should be visible in the interface's behaviour: dates, source lines, distinct states, and copy that says exactly what was and was not checked. A soft gradient hero and floating sample cards imply a marketing product; a ledger implies a record.

The coverage is national. The design should feel like a trustworthy Nigerian public utility: a visible national marker, reviewed state/LGA language, and local density without overclaiming comprehensive coverage. The hero carries two small promise cards — what a check means, and how the national area catalogue works.

## Tokens

| Token | Value | Use |
|---|---|---|
| `--ink` / `--navy` | `#101c3d` | Primary text, primary buttons, brand |
| `--ink-2` | `#243352` | Secondary headings |
| `--paper` | `#f6f3ec` | Page background |
| `--surface` | `#fffdf9` | Cards, panels |
| `--surface-2` | `#eef1f6` | Quiet fills, inert chips |
| `--wire` | `#d9d4c6` | Hairlines, borders |
| `--wire-strong` | `#b9b3a3` | Hover borders, dividers that must read |
| `--muted` | `#55607a` | Secondary text (≥4.5:1 on paper) |
| `--trust` | `#0b6b53` | Check states, links, confirmations |
| `--trust-dark` | `#075442` | Hover/pressed trust |
| `--trust-soft` | `#e3efe8` | Check stamp background |
| `--lime` | `#c6ff00` | Highlighter only: active marker, one underline, focus companion |
| `--alert` | `#9b3a2a` | Errors, destructive actions |
| `--alert-soft` | `#f8e9e4` | Error surfaces |
| `--warn` | `#7a5614` | Deadline urgency text |
| `--warn-soft` | `#fbf1d9` | Urgency surface |

Never place lime text on white or paper. Ink on lime is the only lime pairing.

**Type:** Instrument Sans Variable only. One family, strong scale contrast.
- Display: `clamp(2.4rem, 5.6vw, 4.2rem)`, weight 700, tracking `-0.045em`, line-height `1.02`.
- Section heading: `clamp(1.5rem, 2.6vw, 2rem)`, weight 700, tracking `-0.03em`.
- Card title: `1.18–1.3rem`, weight 700, tracking `-0.025em`.
- Body: `1rem / 1.6`, measure 62–70ch.
- Micro label: `0.72rem`, weight 750, tracking `0.12em`, uppercase.
- Numbers and dates: `font-variant-numeric: tabular-nums`.

**Space:** 4px base. Page gutter `clamp(1rem, 1.4vw, 1.75rem)`, so the shell occupies roughly 96–98% of the viewport at desktop sizes. Section gap `4rem` desktop / `2.5rem` mobile. Card padding `1.15rem` mobile / `1.35rem` desktop.

**Container:** No fixed maximum. The shell fills the viewport minus the gutter. Feed surface uses a `minmax(0,1fr) 19rem` split at ≥1024px. The masthead becomes a two-column headline/search layout at ≥1650px, and the detail page becomes a two-column article/action layout at ≥1200px, so wide screens fill the ledger without long text lines.

**Radii:** 10px controls, 14px cards, 999px chips. No 24px+ rounding on content panels.

**Depth:** borders first. Shadows only on sticky/mobile overlays: `0 12px 30px rgb(16 28 61 / 12%)`. No coloured halos, no blur glows.

**Motion:** 160–220ms `cubic-bezier(0.23, 1, 0.32, 1)`. One authored moment: the feed settles in with a 240ms fade/4px rise, staggered 30ms for the first six rows. Hover lift is 2px with a soft shadow and a single sheen pass on buttons. Reduced motion removes transforms and the feed animation.

**Loading and feedback:** Every route change and filter selection shows a 3px top progress bar plus a small “Loading page” pill. On phones the pill sits above the bottom navigation, so it never covers the headline. The feed uses skeletons and the detail page uses a spinner in the same language. Interactive controls carry a quiet synthesised click, generated with Web Audio (no audio asset); a persistent footer toggle controls it, and sound is never the only feedback for an action.

## Shell

**Desktop header:** 68px, `--surface` with a 1px `--wire` bottom edge. Brand left; a `Nigeria` marker next to it; nav (Explore, Categories, How it works); account actions right. Active nav is ink with a 2px lime underline inset from the text.

**Mobile header:** 60px. Brand, compact national marker, and a menu button for secondary links. Core navigation moves to the bottom bar.

**Bottom nav (≤760px):** four equal items — Explore, Saved, Tracker, Profile. 56px plus `env(safe-area-inset-bottom)`. Active item is trust green with a 4px lime dot above the label. Signed-out users see Profile as "Sign in". The bar is not shown on auth, verification or 404 screens.

**Footer:** editorial, two columns on desktop. Brand + tagline left; a plain-language disclosure right: OyaFind shares public listings, does not submit applications, and does not guarantee outcomes. Mobile: stacked above the bottom nav.

## Explore (home)

1. **Masthead** — national eyebrow (`NIGERIA-WIDE · 36 STATES + FCT`), display headline with a short slanted lime stroke, one-sentence promise, search field, area button, category chips, and two promise cards (checks, national coverage). No hero illustration and no sample listings.
2. **Feed** — section heading with live count and sort. Rows begin in the first viewport on a 390×844 phone (masthead is capped at ~430px tall).
3. **Right rail (desktop)** — "What a check means", "The 30-day rule", "Saving is private". Sticky below the header. On mobile these become a compact disclosure below the feed.
4. **Below the feed** — a short "How it works" three-step band linking to the full page. Categories live in the chip row and on `/categories`, not in a second wall of cards.

**Empty feed:** not an error. A ruled panel states that no live listings match this view, that listings appear only after review and a source/detail check, and offers area/search resets. Never insert example listings into the live feed.

**Search/filter state:** the URL owns query, category, location and sort. Chips show the active filter and offer Clear.

## Opportunity card

A ledger row, not a promotional tile.

- Top line: category label (small caps, ink), work mode, then deadline at the far right. Deadline within 7 days gains `--warn` and says "Closes in 3 days"; otherwise "Closes 12 Nov".
- Title is an `<h3>` link, two lines max.
- Organisation · Location on one line, muted, with a pin glyph.
- Summary, two lines max.
- Footer: trust stamp left (`shield` + "Source checked 12 Oct" in trust green), actions right (`Save`, `View`).
- The entire row is not a link; only the title and explicit actions are. This keeps Save usable with a keyboard.

**Trust stamp:** always a date or an explicit "Check date not recorded". Tooltip/title: "OyaFind checked the source and details on 12 Oct 2026. A check is not an endorsement or guarantee." Never use the word "verified" as a decorative adjective.

## Opportunity detail

- Back link, then a title block with category, work mode, deadline.
- Organisation and location directly under the title.
- **Check panel** — two separate rows: `Published on OyaFind` (ink dot) and `Source and details checked 12 Oct 2026` (trust check). A third line states what the check does not guarantee. This panel is the visual centre of the page.
- Facts grid: Deadline, Location, Eligibility, How to apply, Requirements. Two columns desktop, one mobile.
- Description and application instructions.
- Source box with the provider link and a plain warning to check before sharing personal data.
- Sticky action bar on mobile: Save + Continue to provider. On desktop these sit inline at the end of the article.
- No fake report link. Reporting is not implemented in this build.

## Saved

Header with count, then either the list or an empty state that explains what saving does. Rows reuse the ledger card with `Remove` and `Track progress`. Unavailable listings keep a visible "No longer public" state without leaking restricted fields. Tracking opens a modal with stage, date and private notes; the modal states that records are user-reported.

## Tracker

Header, a privacy banner, status filter chips with counts, then application records. Each record shows the user-reported stage, the opportunity (if still public), applied date and private notes. Editing happens inline. Empty states distinguish "nothing tracked yet" from "nothing in this stage".

## Auth and profile

Keep the existing split-panel composition: navy story panel with the mark, paper form panel. The story panel earns its space with three concrete truths (reviewed before publication, provider's own application channel, private tracker). Forms use 44px controls, visible labels, inline field errors, and a single primary action.

Profile keeps the two-column settings layout, but the verification state is explicit: selected contact, verified/unverified, and what saving requires. No decorative account statistics.

## Categories and How it works

These are guide pages, not catalogues of promises. Category cards keep the icon + audience + common types, but remove invented per-category check claims. One shared standard is stated once. How it works is four steps and three trust commitments, all traceable to `PRODUCT.md`; no fee promises, no implied report workflow.

## States

- **Loading:** skeleton lines for the feed (not a centred spinner for the primary list); small spinner for secondary actions.
- **Empty:** ruled panel, one clear next action.
- **Error:** plain language, retry, and a route back. Never blame the user.
- **Offline/stale:** visible timestamp and "may be out of date"; no pretending cached listings are live.
- **Unverified contact:** inline explanation at the point of saving, with a link to Profile. Never a silent disabled button.
- **Signed out:** an auth gate that preserves the intended destination.

## Copy rules

1. Say "source and details checked", not "verified opportunity".
2. Show the check date or say it is not recorded.
3. Say "Nigeria-wide" / "36 states + FCT" when describing coverage; never claim to list every opportunity in the country.
4. No fee, safety, speed or outcome guarantees.
5. No sample listings presented as real.
6. No claims about reporting, alerts or notifications that the build cannot keep.
7. Prefer concrete nouns and dates over adjectives.

## Verification

Check at 390×844, 768×1024 and 1440×900: first-viewport feed presence, no horizontal scroll, 44px touch targets, keyboard order, focus visibility, contrast, long titles/emails, zero-result and error states, and the signed-out save gate. Run `npm run test:js`, `php artisan test`, `npm run build`, and Pint before handoff.
