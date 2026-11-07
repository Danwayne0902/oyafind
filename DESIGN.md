# OyaFind — Design

**Direction:** *Studio.* A modern product surface with editorial bones: warm
stock, deep ink, one electric lime signal, generous rounding, real elevation,
and large, confident type. The page should feel drawn by a product designer who
also wrote the front end — every spacing value, hover state and transition has
a reason.

**Surface mode:** Operate (browse, assess, save, track), with a short cinematic
opening on the first visit of a session. Ambition shows as finish, not noise.

**Brand commitments:** the existing mark and navy are fixed. Lime is a signal —
a marker, one underline, a scroll hairline, a focus companion — never body text
and never a full-surface colour. Warm paper and a single trust green carry the
rest.

## Why this direction

The product's promise is that someone checked before publishing. That promise
has to be visible in the interface's behaviour: dates, source lines, distinct
states, and copy that says exactly what was and was not checked. A generic SaaS
hero with floating gradients undermines it. A warm, precise product surface
with an editorial numbering system supports it.

The coverage is national. The design should feel like a trustworthy Nigerian
public utility: a visible national marker, reviewed state/LGA language, and
local density without overclaiming coverage. States and LGAs are never mixed in
one list.

## Layers

There is one public stylesheet: `resources/css/studio.css`, loaded from
`resources/js/app.js`. It holds two sections:

| Section | File | Role |
|---|---|---|
| 1 | `studio.css` → Legacy foundations | structural base and surviving rules from the 2024–2026 layers. Frozen: do not extend. |
| 2 | `studio.css` → Studio layer | **authoritative visual layer**. All new work goes here. |

The original `app.css`, `oyafind-v2.css` and `edition.css` were merged into the
legacy section; declarations that a later layer already overrode were dropped.
If a legacy rule wins on specificity, override it in the Studio section rather
than editing the foundations.

## Tokens

| Token | Value | Use |
|---|---|---|
| `--paper` | `#f7f5f0` | page stock |
| `--paper-2` | `#efebe1` | quiet fills, chips |
| `--surface` | `#ffffff` | cards, panels, controls |
| `--surface-2` | `#f2efe7` | inset blocks, quiet chips |
| `--ink` / `--navy` | `#0e1330` / `#101c3d` | text, primary buttons, dark bands |
| `--ink-2` | `#2f3854` | secondary headings |
| `--ink-3` / `--muted` | `#6a7290` | body secondary, metadata (≥4.5:1 on paper) |
| `--wire` | `#e6e1d6` | card borders, hairlines |
| `--wire-strong` | `#d3ccbc` | hover borders, section rules |
| `--trust` | `#0b6b53` | checked states, confirmations |
| `--trust-dark` | `#075442` | trust text on light surfaces |
| `--lime` | `#c6ff00` | marker, scroll hairline, focus ring companion |
| `--alert` | `#a4321d` | errors, destructive actions |
| `--warn` | `#8a4a12` | deadline urgency |

Never set lime text on white or paper. Ink on lime is the only lime pairing.
Lime on navy is allowed for the single signal word or stat in a dark card.

**Light**

- `--shadow-sm`: `0 1px 2px rgb(14 19 48 / 5%), 0 6px 16px -12px rgb(14 19 48 / 22%)`
- `--shadow-md`: `0 2px 6px rgb(14 19 48 / 5%), 0 20px 40px -24px rgb(14 19 48 / 32%)`
- `--shadow-lg`: `0 40px 90px -50px rgb(14 19 48 / 45%)`
- `--shadow-lift`: hover elevation for feed and category cards

Elevation is layered and soft. No coloured glows, no blurred gradients behind
text.

## Type

Instrument Sans Variable only. One family, strong scale contrast.

- Hero: `clamp(2.7rem, 5.3vw, 4.5rem)` at ≥1180px up to `4.6rem`, weight 700,
  tracking `-0.052em`, line-height `0.97`, `text-wrap: balance`.
- Detail title: `clamp(2rem, 4vw, 3.15rem)`, weight 700, tracking `-0.05em`.
- Page title: `clamp(2.2rem, 4.4vw, 3.4rem)`, weight 700.
- Section heading: `clamp(1.75rem, 2.6vw, 2.15rem)`, weight 700.
- Card title: `1.12–1.3rem`, weight 640–660, tracking `-0.028em`.
- Body: `1rem / 1.6`, measure 52–66ch.
- Micro label: `0.66–0.72rem`, weight 620–660, tracking `0.14–0.16em`,
  uppercase. One per block, never stacked.
- Numbers, dates, counts: `font-variant-numeric: tabular-nums`.

Body copy uses `text-wrap: pretty`; headings use `text-wrap: balance`.

## Space and geometry

- 4px base rhythm.
- Page gutter: `clamp(1.15rem, 3.6vw, 2.5rem)`.
- Container: `1240px`; `.page-content` and `.header-inner` share the same width
  so header and content edges line up.
- Header height: `4.75rem` desktop, `4.1rem` phones.
- Section gap: `clamp(3rem, 6vw, 5rem)` between major bands.
- Card padding: `1.2–1.6rem`, `1.15rem` on phones.
- Radii: `20px` cards, `18px` feed and ledger cards, `12px` controls and
  buttons, `10px` inputs, `999px` chips and actions that read as pills.
- Full-bleed bands (`.how-band`, `.site-footer`) use
  `margin-inline: calc(50% - 50vw)` with `width: auto` and
  `max-width: none`; the inner content is padded back to the container.

## Signature elements

**Editorial numbering.** Feed cards and category cards carry a small tabular
index in the left margin (`01`, `02`, …). On phones the index is hidden so the
title gets the full width.

**The lime marker.** The hero phrase is wrapped in `.masthead-highlight`. A lime
bar sits behind the lower third and sweeps in once, left to right. It is not a
static background: `.is-ready` fires the 700ms sweep.

**Dark bands.** The “how it works” band, the ledger hero card, the checked rail
card, the auth story panel and the footer use the same navy radial:
`radial-gradient(120% 120% at 100% 0%, #1d2f61 0%, #101c3d 58%)`. Lime is used
once per dark surface as a signal.

**Pills and cards.** Chips are true pills. Cards are white, 1px warm borders,
soft elevation, and lift 2–3px on hover. Buttons are 12px-radius ink blocks;
secondary buttons are white with a strong warm border.

**Detail layout.** At ≥1024px: a sticky navy-free action rail on the right
(`detail-act`), a check panel above it, facts in a bordered two-column block,
and long copy in a white card. On phones the action rail becomes a normal block
after the copy.

## Motion

All motion is CSS transitions plus one `IntersectionObserver` and one passive
scroll listener in `resources/js/motion.js`. No animation library.

**First visit of a session (cinematic sequence)**

1. `0ms` — navy curtain covers the viewport with the wordmark and a lime rule
   drawing to 48px.
2. `130ms` — the curtain lifts upward with `clip-path`, 780ms
   `cubic-bezier(0.65, 0, 0.35, 1)`. The wordmark fades and lifts 10px.
3. `620ms` — while the curtain clears the hero, the two headline lines rise
   from below their masks over 1000ms (`cubic-bezier(0.16, 1, 0.3, 1)`), the
   second line +90ms.
4. `1500ms` — the lime marker sweeps under the hero phrase over 700ms.
5. `2600ms` — the intro class is removed; later visits in the session skip the
   curtain and run the hero sequence immediately.

The intro adds `is-intro` to `<html>` only when the curtain is actually shown, so
a returning visitor or a reduced-motion visitor never waits for an animation
that is not happening.

**Scroll**

- Elements marked `data-reveal` start at `opacity: 0`, `translateY(26px)` and
  `blur(6px)`, then clear over 720ms with a 60ms per-item stagger
  (`--reveal-i`, capped).
- The header condenses on scroll: background opacity, border, shadow, over
  320ms. A 2px lime hairline under the header shows `--scroll-progress`.
- The hero card column drifts 3.5% of the scroll distance for depth.
- The hero dateline keeps its small parallax drift.

**Route changes**

- `.page-swap` runs a 480ms fade/10px rise keyed by route.
- The loading pill is a white rounded pill; it is hidden during the intro.

**Hover and press**

- Buttons lift 1px and darken; press returns to 0 and scales `0.99`.
- Feed cards lift 3px and take `--shadow-lift`.
- Nav links draw a 1.5px ink underline from the left over 320ms.
- Chips invert to ink on hover with a lime icon.
- Save becomes lime when saved; “View” nudges its circular arrow.

**Reduced motion**

`prefers-reduced-motion: reduce` collapses every animation and transition to
1ms, removes the curtain, shows all `data-reveal` content immediately, and
disables `scroll-behavior: smooth`. The page is complete without motion.

## Feedback and states

- Loading: route pill plus skeletons in the feed; the detail page uses a
  spinner in the same pill language.
- Empty: a white card with a heading, one sentence and a primary action; no
  decorative illustration.
- Error: the same card with an alert border and the API message.
- 404: unknown URLs return a real HTTP 404 and still boot the shell, so the
  branded not-found card, header and footer render instead of Laravel's default
  error page.
- Toasts: white card, ink border, `--shadow-lg`, one action link.

## Content rules

- No fabricated verification, coverage or ranking claims.
- Every public listing shows a reviewed location; “Location not specified”
  must not appear in the UI.
- Areas are hierarchical: a state expands to its own LGAs, a region expands to
  its countries, and the two are never mixed in one list.
- A check is not an endorsement or a guarantee. Copy says what was checked and
  when, and what OyaFind does not do (apply, decide, guarantee).
- Publishing requires a reviewer and a different verifier.

## Accessibility

- Visible focus on every control; inputs get a 3px lime-adjacent focus ring.
- Body text meets 4.5:1 on paper and white; `--ink-3` is the lightest text
  used for body-sized copy.
- Touch targets are at least 40px high (44px for primary actions).
- Motion is decorative and removable; the page never depends on animation to
  reveal content.
- Icon-only controls carry `aria-label`s; the skip link is the first tab stop.
