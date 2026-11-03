# OyaFind — project rules for AI agents

These rules are permanent instructions from the project owner. Follow them in
every session, even when a task seems to make an exception obvious.

## Database safety (non-negotiable)

- **Never modify, migrate, truncate, or delete the production database without
  a fresh backup first.** Create the backup with
  `sudo OYAFIND_DIR=/opt/oyafind /opt/oyafind/deploy/backup.sh` and confirm the
  new `db-*.sql.gz` file exists in `/var/backups/oyafind` before proceeding.
- **Never run tests against the production database.** The PHP test suite uses
  SQLite in memory (`phpunit.xml`); keep it that way. If a task needs MySQL
  behaviour, create a temporary database (for example
  `oyafind_agent_scratch`) inside the MySQL container, use it, and drop it
  afterwards.
- Do not drop, rename, or recreate the `oyafind` database or the
  `oyafind` MySQL user. Do not restart `mysql` with an empty volume.
- Prefer additive migrations. A destructive migration (`dropColumn`,
  `dropTable`, data backfills) needs a backup, a dry run, and an explicit
  confirmation from the owner.
- Treat every data fix as a reviewed change: put the evidence in a CSV under
  `database/seeders/data/` and apply it through an artisan command that
  validates before writing, rather than editing rows by hand.

## Environment and secrets

- `.env` is never committed, never served, and never logged. Only
  `.env.example` and `deploy/.env.production.example` belong in git.
- Do not print secrets into chat or logs. When the owner asks for connection
  details, give them the values on request, scoped to what they need.

## Deployment

- Production host: `ubuntu@145.241.204.16`, repo at `/opt/oyafind`, compose
  files in `/opt/oyafind/deploy` (root-owned; use `sudo git pull --ff-only`).
- Database access: `db-access` publishes `127.0.0.1:13306` on the VM; connect
  through `ssh -N -L 13306:127.0.0.1:13306 ubuntu@145.241.204.16`. Credentials
  live in `deploy/.env` (`DB_USERNAME`/`DB_PASSWORD`). Never publish MySQL on a
  public interface.
- Code changes require `sudo docker compose build app scheduler web` and
  `up -d --force-recreate app scheduler web`; images have no bind mounts, so
  CSV, view, and PHP changes only appear after a rebuild.
- Run the full PHP and JS suites before pushing:
  `php artisan test` and `npm run test:js`.
- After deploying, verify with real requests: status codes, headers, and one
  page of each affected type.

## Product invariants

- No fabricated verification, coverage, or ranking claims.
- Every public listing has a reviewed location; "Location not specified" must
  not appear in the UI.
- Areas are hierarchical: a state expands to its own LGAs, a region expands to
  its countries, and the two are never mixed in one list.
- Publishing requires a reviewer and a different verifier.
- Top-3 Google ranking cannot be promised; only the technical foundation and
  honest content can be delivered.
