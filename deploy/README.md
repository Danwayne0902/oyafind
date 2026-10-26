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

## Opening the publication pipeline

Nothing publishes until two different people act. The policy lives in
`deploy/.env` and fails closed: if a reviewer or verifier id is missing, the
public feed stays empty rather than guessing.

1. **Sign in as the seeded administrator** (`admin@oyafind.ng`, password from
   the provisioning output) and change the password.

2. **Create a second staff account.** Reviewer and verifier must be different
   users:

   ```bash
   cd /opt/oyafind/deploy
   docker compose exec -T app php artisan tinker
   ```

   ```php
   \App\Models\User::create([
       'name' => 'OyaFind Verifier',
       'email' => 'verifier@oyafind.ng',
       'password' => bcrypt('a-long-password'),
       'role' => 'staff',
       'status' => 'active',
       'email_verified_at' => now(),
   ]);
   ```

3. **Note both ids** (`\App\Models\User::pluck('email', 'id')`) and set them:

   ```ini
   OYAFIND_PUBLIC_STAFF_REVIEWER_IDS=1
   OYAFIND_PUBLIC_VERIFIER_IDS=2
   ```

4. **Recreate the containers** so the cached config picks up the change.
   Editing `.env` alone does not restart anything, and cached config would
   otherwise keep the old ids:

   ```bash
   docker compose up -d --force-recreate
   ```

5. **Ingest, review, verify:**

   ```bash
   docker compose exec -T app php artisan opportunities:ingest
   docker compose exec -T app php artisan opportunities:pending --status=pending
   docker compose exec -it app php artisan opportunities:review
   docker compose exec -it app php artisan opportunities:pending --status=reviewed
   docker compose exec -it app php artisan opportunities:verify
   ```

   Ingestion also runs every 30 minutes from the scheduler container. It only
   refills the review queue — it can never publish.

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
