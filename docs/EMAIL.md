# OyaFind brand email

A brand address has to satisfy two separate things, and they are often confused:

- **Official** — it is on the domain you control (`@oyafind.com.ng`), so the
  brand name appears in it and you can never lose it to a provider's policy
  change. A free `@gmail.com` address is neither official nor portable, and
  some platforms reject it outright: LinkedIn company pages, for example,
  refuse free-mail domains.
- **Working** — mail to it actually arrives, and mail from it passes SPF, DKIM
  and DMARC so it does not land in spam or get rejected.

Free hosting can give you both. It cannot give you a *guaranteed* inbox
reputation; that is earned by sending legitimate mail and is checked below.

## Current state (verified 2026-11-04)

`./scripts/mail-check.sh` reports:

| Check | State |
|---|---|
| MX (can receive) | **missing** — mail to `hello@oyafind.com.ng` bounces |
| SPF (authorised senders) | **missing** |
| DKIM (signed mail) | **missing** at every usual selector |
| DMARC (policy) | **missing** |

The application already sends as `hello@oyafind.com.ng` through Brevo
(`MAIL_HOST=smtp-relay.brevo.com`, `MAIL_FROM_ADDRESS="hello@oyafind.com.ng"`).
Sending without SPF/DKIM/DMARC is why provider mail can look forged, and with
no MX record every reply to that address is lost. Both halves need fixing
before the address is used to register social accounts, because those accounts
send verification codes *to* it.

## Option A — Zoho Mail Free (recommended)

A real mailbox on your own domain, free forever, no card.

| Item | Free plan |
|---|---|
| Users | up to 5 |
| Storage | 5 GB each |
| Domains | 1 |
| Access | Zoho webmail and mobile app **only** — no IMAP/POP/ActiveSync, so no Outlook/Apple Mail |
| Cost | 0 |

The web-only limitation does not matter for a brand inbox used for replies and
verification codes. It does matter if you want the address inside Gmail or
Apple Mail; that requires Mail Lite (about $1/user/month).

Free-plan availability depends on the data centre assigned to your region. If
signup pushes you to a paid plan, use Option B or C instead of paying.

**Steps**

1. Sign up at `zoho.com/mail` → *Business Email* → *Forever Free*.
2. Verify the mobile number (Zoho requires SMS verification).
3. Add the existing domain `oyafind.com.ng`; Zoho gives you a TXT record to
   prove ownership. Add it at AfeesHost (your DNS host) and verify.
4. Create the mailbox `hello@oyafind.com.ng` and note the exact **MX** and
   **SPF** values Zoho's console shows for your data centre — they differ by
   region, so copy them rather than using values from a blog.
5. Add the MX records **after** the mailbox exists, so mail is never sent to a
   server that does not yet have the account.

## Option B — the mailbox your registrar may already include

`oyafind.com.ng` uses `dns1.afeeshost.ltd` nameservers. Nigerian registrars
usually bundle cPanel hosting with a domain, and cPanel includes email accounts
with webmail (Roundcube) plus IMAP/SMTP. If that plan is active:

1. Log in to the AfeesHost client area → cPanel → *Email Accounts*.
2. Create `hello@oyafind.com.ng`.
3. Confirm the MX record points at the provider's mail server (cPanel usually
   adds it, and *Email Deliverability* in cPanel shows SPF/DKIM health).

This is the least work **if** the plan exists, and it keeps everything with one
provider. It is not worth renewing a hosting plan just for email — Option A is
free and independent of AfeesHost.

## Option C — Cloudflare Email Routing (forwarding only)

Free and unlimited inbound forwarding, but it only *receives*: mail to
`hello@oyafind.com.ng` is forwarded to an inbox you already own, and sending
still has to go through Brevo. Useful as a safety net so no message is ever
lost, not as the whole answer. It also requires moving the domain's nameservers
to Cloudflare, which changes who controls DNS — a bigger decision than it looks.

## DNS records to add at AfeesHost

Replace the placeholders with the exact values from your chosen provider.

| Type | Name | Value | Purpose |
|---|---|---|---|
| MX | `@` | e.g. `mx.zoho.com` (10), `mx2.zoho.com` (20) | receive mail |
| TXT | `@` | `v=spf1 include:spf.brevo.com include:<mail-provider-spf> -all` | authorise senders |
| TXT | `<selector>._domainkey` | the long `k=rsa; p=...` value from Brevo | DKIM signing |
| TXT | `_dmarc` | `v=DMARC1; p=none; rua=mailto:dmarc@oyafind.com.ng; fo=1` | policy + reports |
| TXT | `@` | provider's domain-ownership token | verification |

Rules that save an afternoon:

- **One SPF record only.** Two `v=spf1` records break authentication; merge
  every sender into one line.
- Keep `include:spf.brevo.com` — the application depends on it.
- Start DMARC at `p=none` and watch the reports for two weeks; move to
  `p=quarantine` only when legitimate mail is passing.
- Never publish `p=reject` before SPF and DKIM both pass, or you will silently
  bin your own mail.

## Proving it works before using it anywhere

1. `./scripts/mail-check.sh` — every line must read PASS.
2. From the new mailbox, send a message to a Gmail address you own and open
   *Show original*. `SPF: PASS`, `DKIM: PASS`, `DMARC: PASS` must all appear.
3. Reply to it from Gmail and confirm it arrives (this is the half that is
   currently broken).
4. Send a test through the site's own mailer:
   `php artisan tinker` in the app container is not needed — register a test
   account only after MX works, or use Brevo's *Send a test* button.
5. Optional: run `mail-tester.com` once; aim for 9/10 or better.

## Using the address for social accounts

- Use `hello@oyafind.com.ng` for the accounts, and keep `admin@` for anything
  that controls billing or recovery. One shared brand inbox is easier to defend
  than five.
- Turn on two-factor authentication on the mailbox before creating the social
  accounts. The mailbox becomes the recovery path for every one of them, so it
  is the single most valuable account you will own.
- Use a unique password, stored in a password manager — not the one used for
  `/admin`.
- Keep a record of which address opened which account; losing that mapping is
  how brands get locked out.
- Most platforms still ask for a phone number. Use a number you will keep; the
  same number can verify several accounts, but it becomes a single point of
  failure.

## Legal and official position

- **Ownership.** The domain is registered in your name with a NiRA-accredited
  registrar; the mailbox on it inherits that. Nothing else is needed for the
  address itself to be lawful.
- **Do not claim what is not true.** A domain mailbox is not a registered
  business. If you register a business name with the CAC, publish the RC number
  on the site and socials; until then, describe OyaFind as a brand, not a
  registered company. Fabricated registration claims are both a legal risk and
  a platform-policy risk.
- **Data protection.** Once `hello@oyafind.com.ng` is published, people will
  send personal data to it. The Nigeria Data Protection Act 2023 applies to how
  you store and use that; the practical minimum is a privacy page naming what
  you collect and how to ask for deletion, plus not forwarding the inbox to
  personal accounts you do not control.
- **Consistency.** Use the same brand name, logo and address everywhere. Social
  platforms use cross-references between the site, the mailbox and the profile
  when deciding whether an account is genuine.

## Known gaps on the site itself

There are no `/privacy` or `/terms` routes yet, and the footer does not publish
a contact address. Both are worth adding before opening social accounts, since
several platforms ask for a privacy-policy URL and reviewers look for a real
contact route.
