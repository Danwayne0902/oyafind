# Deploying OyaFind

This directory turns the repository into a running site on a single Linux VM:
MySQL, PHP-FPM, a scheduler and Caddy with automatic HTTPS, all in Docker.

The reference target is **Oracle Cloud Always Free** (Ampere A1, 2 OCPU / 12 GB,
Ubuntu 24.04), which costs nothing and stays on continuously. Any Ubuntu VPS
with 2 GB of RAM works the same way.

---

## What this stack is honest about

| | |
|---|---|
| **Cost** | ₦0 for compute and TLS. The domain is the only real cost. |
| **Public domain** | `oyafind.com.ng` — about ₦7,000/year from a NiRA-accredited registrar. There is no free `.ng` or `.com.ng`. |
| **Free alternative** | `oyafind.is-a.dev` or `oyafind.duckdns.org` work with this stack by changing one value. They look less credible to Nigerian users. |
| **Uptime** | One VM, no SLA, no redundancy. Fine for launch; not for scale. |
| **Backups** | Nightly dump to the same disk, kept 14 days. Copy them off-box if the data matters. |

---

## Before you start

1. **Register the domain.** Do this first; certificate issuance waits on DNS.
2. **Create the Oracle instance.** Always Free → Ampere A1 → Ubuntu 24.04 →
   2 OCPU / 12 GB → assign a public IPv4.
3. **Reserve the IP.** Instance details → Resources → Attached VNICs → IPv4
   address → Edit → *Reserved public IP*. Without this, a reboot can hand you a
   new address and break DNS.
4. **Upgrade the account to Pay As You Go.** It still bills ₦0 while you stay
   inside the Always Free limits, and it removes the idle-instance reclamation
   policy that can silently delete an Always Free VM. This is the single
   biggest reliability win available for free.

---

## Deploy

```bash
ssh ubuntu@<instance-ip>

sudo APP_DOMAIN=oyafind.com.ng \
     ACME_EMAIL=you@example.com \
     OYAFIND_REPO=git@github.com:Danwayne0902/oyafind.git \
     bash -c "$(curl -fsSL https://raw.githubusercontent.com/Danwayne0902/oyafind/main/deploy/provision-oracle.sh)"
```

If the repository is private, clone it yourself and run the script from the
checkout instead:

```bash
git clone git@github.com:Danwayne0902/oyafind.git /opt/oyafind
cd /opt/oyafind
sudo APP_DOMAIN=oyafind.com.ng ACME_EMAIL=you@example.com ./deploy/provision-oracle.sh
```

The script installs Docker, opens ports 80/443 on the host firewall, generates
`deploy/.env` with random database and admin passwords, builds the images,
starts the stack, seeds reference data, and installs the nightly backup job.
It prints the generated admin password once.

### Then, in the Oracle console

Networking → Virtual Cloud Networks → your VCN → Security Lists → *Default
Security List* → Add Ingress Rules:

| Stateless | Source | Protocol | Destination Port |
|---|---|---|---|
| No | `0.0.0.0/0` | TCP | `80` |
| No | `0.0.0.0/0` | TCP | `443` |

The VM firewall is already open. Oracle has two layers and both must allow the
traffic — this is the most common reason a deploy "doesn't load".

### Then DNS

| Type | Name | Value | TTL |
|---|---|---|---|
| A | `@` | your reserved public IP | 300 |
| CNAME | `www` | `oyafind.com.ng` | 300 |

Caddy retries certificate issuance on its own, so a slow DNS propagation only
delays HTTPS — it does not require a restart.

### Verify

```bash
curl -I https://oyafind.com.ng/up          # expect 200
curl -s https://oyafind.com.ng/api/v1/opportunities | head -c 400
cd /opt/oyafind/deploy && docker compose ps
```

---

## Accounts and registration

`OYAFIND_ACCOUNT_REGISTRATION=true` opens sign-up. `OYAFIND_REGISTRATION_MODE`
controls what happens after the form is submitted:

| Mode | Behaviour |
|---|---|
| `direct` | The account is active immediately. Email or phone plus a password. The contact stays unverified, so actions that require verification (saving) stay locked. No mail is sent. |
| `email_verification` | Only email addresses can register. The account stays `pending` until the signed link is opened. Requires a working mailer (see **Mail**). |

Switching to `email_verification` later does not retroactively verify existing
contacts.

`db:seed` creates the administrator and, when `OYAFIND_SEED_VERIFIER_PASSWORD`
is set, a second account (`verifier@oyafind.ng`). Publishing needs two different
allowlisted accounts, so that second account must exist before anything can go
live.

---

## Admin panel

Sign in at `/login` with an administrator account, then open `/admin`. The panel
is server-rendered (Blade) and shares the same session as the public site.

- **Listings** lists drafts, approved listings and live listings.
- **New listing** saves a draft with a title, summary, description, category,
  location, source link and either a deadline or a duration in days.
- **Approve for publishing** records the staff approval (with the source
  checked or a rationale) and queues the listing for verification.
- **Publish listing** is only offered to the allowlisted verifier account. It
  writes the required check records and makes the listing public.
- **Withdraw** hides a live listing without deleting it. **Delete** soft-deletes
  it.

The panel follows the same evidence rules as ingestion: a staff approval and a
separate verifier are always recorded, and the public feed only shows listings
that satisfy the policy in `deploy/.env`.

Ingested candidates are still handled from the CLI:

```bash
docker compose exec -T app php artisan opportunities:ingest
docker compose exec -T app php artisan opportunities:review --reviewer=1
docker compose exec -T app php artisan opportunities:verify --verifier=2
```

---

## Mail

Account registration sends a verification link, so registration must stay off
(`OYAFIND_ACCOUNT_REGISTRATION=false`) until SMTP works. Free options that
behave from a VPS:

- **Brevo** — 300 emails/day, SMTP relay.
- **Resend** — 3,000 emails/month, SMTP.

Set `MAIL_USERNAME` and `MAIL_PASSWORD` in `deploy/.env`, restart, then flip
registration on. Delivery from a brand-new domain is imperfect until SPF and
DKIM records exist; adding them is worth the twenty minutes.

---

## Operations

The provisioning script clones and builds as root, so run these with `sudo`.
They must be run from `/opt/oyafind/deploy`.

```bash
cd /opt/oyafind/deploy

sudo make ps            # container status
sudo make logs          # follow everything
sudo make deploy        # pull, rebuild, migrate
sudo make backup        # dump now
sudo make review        # pending queue
```

**Updating code**

```bash
sudo git -C /opt/oyafind pull --ff-only
cd /opt/oyafind/deploy && sudo make deploy
```

**Restoring a backup**

```bash
make restore-db FILE=/var/backups/oyafind/db-20261026T021500Z.sql.gz
```

**Renewing certificates** — automatic. Caddy renews at 30 days remaining and
persists them in the `caddy-data` volume.

**Disk** — the stack idles around 1.2 GB of RAM and grows the database slowly.
`docker system prune -af --volumes` is safe only after you have restored from a
backup at least once, because it can remove images you would need mid-rollback.

---

## What this deployment does not do

- **No staff web UI.** Review and verification are CLI-only.
- **No user uploads served.** Caddy serves a copy of `public/`; the
  `public/storage` symlink would not resolve there. Nothing in the current
  build uploads files.
- **No queue worker.** The queue is `sync`; nothing is queued.
- **No object storage.** Backups are local. Move them off-box if the listings
  become the only copy of anything.
- **No horizontal scale.** One VM, one database, one scheduler. Correct for
  this stage, wrong later.

---

## Troubleshooting

**Site does not load, SSH works**
The Oracle security list is still closed, or the host firewall rule was not
persisted. Check `sudo iptables -L INPUT -n --line-numbers | head` for ACCEPT
rules on 80/443.

**`502` from Caddy**
PHP-FPM is not answering. `docker compose logs app` — usually a migration
failure or a wrong `DB_PASSWORD` in `deploy/.env` (change it there *and* accept
that MySQL initialised with the old one; recreating the `mysql-data` volume
wipes the database).

**Certificate errors**
DNS is not pointing at this VM yet, or port 80 is blocked. Let's Encrypt
validates over HTTP. Confirm with `curl -I http://oyafind.com.ng/.well-known/acme-challenge/test`.

**Empty public feed**
Discovery is failing closed. Check that both reviewer and verifier ids are set,
that they differ, that both accounts are active, and that approved listings
have passed `opportunities:verify`.
