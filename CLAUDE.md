# PSV Tracker — Project Guide

## What this is
Server and apps for tracking Barbados public service vehicles (minibuses, ZRs,
route taxis). A driver Android app signs on to a route and streams GPS pings;
this server captures them and serves an admin map portal and per-owner
dashboards. Riders use it free; the eventual funding model is government data
plus advertising.

## Stack and conventions
- PHP (no framework) + MariaDB/MySQL + nginx + php-fpm on Ubuntu. Native
  packages, not Docker.
- Match the existing `api/` style: PDO with prepared statements, JSON responses,
  DB credentials read **only** from `api/config.php`.
- `api/config.php` is generated on the server by `install.sh` (random password)
  and is git-ignored. Never commit it, or any credentials, to the repo.
- Schema changes go in **new** numbered migrations: `schema/002_*.sql`,
  `schema/003_*.sql`, etc. Never edit a migration that has already been applied.
  `schema/migrate.sh` applies each file once and records it in
  `schema_migrations`.
- Frontend: vanilla JS + Leaflet (from CDN) with OpenStreetMap tiles. No build
  tooling.
- On the server the repo lives at `/opt/psv-tracker`.

## Deploy workflow (do not break this)
- Fresh install:
  `curl -sSL "https://raw.githubusercontent.com/caritechsolutions/psv-tracker/main/install.sh?v=$(date +%s)" | sudo bash`
- Update after a push: `sudo /opt/psv-tracker/update.sh`
  (git pull, apply any new migrations, reload nginx).

## Current state (built and tested — don't modify unless asked)
- `api/` — capture endpoints `signon.php`, `ping.php`, `signoff.php`, plus shared
  `db.php` (bearer-token auth, JSON helpers).
- `schema/001_init.sql` — tables `drivers`, `vehicles`, `routes`, `shifts`,
  `positions`; seeds a test driver, vehicle id 1, route id 1.
- **The public test token `test-token-abc123` is REVOKED** (migration
  `010_revoke_test_token.sql`) because it shipped in the repo. It returns 401
  everywhere. Do NOT reintroduce it in any new migration. Tests must obtain a
  private capture token via `api/driver-login.php` (driver `rrrrr` has a password
  and a vehicle grant) and use that as the `Authorization: Bearer` token.
- `install.sh`, `update.sh`, `nginx/psv-tracker.conf`.

## How to work in this repo
- Ask clarifying questions before guessing. Propose the approach/architecture
  first and wait for an OK before writing code.
- Build in small steps; let me confirm each one works before moving to the next.
  No scope creep beyond the current step.
- Don't touch the capture `api/` or any already-applied migration unless asked.

## Roadmap

Done:
1. Capture API — driver sign-on / ping / sign-off. Auth is now per-issued token
   (driver_tokens, via driver-login.php); sign-on enforces vehicle-access grants.
2. Admin portal — login + live Barbados map of signed-on vehicles.
3. Admin shell — left nav rail + layout partial; map as a full-bleed map with a
   collapsible overlay control panel.
4. Ads configuration — image banner ads, global rotation, served via /api/ads.php.
5. Fleet management — vehicles (capacity + owner), drivers, routes. Owners manage
   their own drivers and grant them access to specific vehicles.
6. Owner portal — owners see only their own vehicles + telemetry and manage their
   own drivers/grants (separate owner session).
7. Rider web app (v1) — served at /app/ (bare domain redirects there). Public
   live map with route filter, seat status, the rider's GPS + nearest van, and
   the ad rotation — the public feed carries NO driver identity. Rider accounts
   (separate session, rate-limited login). Proximity-gated check-in awards one
   trip point per verified ride. Ride ratings: a PUBLIC vehicle aggregate (on the
   map) + a PRIVATE driver aggregate (owner + admin only, never public).
   Security pass also landed: public test token revoked, login rate-limiting,
   per-login-IP throttling behind the proxy.

Later:
8. Ride rewards v2 — distance-travelled points, confirmed via co-movement
   matching (rider speed/heading/stop-go tracks a tracked vehicle). Server-side
   anti-fraud: mock-location/GPS-spoof detection, route/speed plausibility, rate
   caps. Battery-friendly: track only during an active trip, foreground-service
   notification, sensible cadence. (v1 ships trip-based points + a soft GPS
   proximity gate; this hardens it.)
9. GPS occupancy — infer seats-available from co-moving rider devices; only
   viable once app penetration is high (undercounts otherwise). Automatic
   passenger counters (door sensors) are the accurate-count upgrade when the
   per-vehicle hardware cost is justified.
10. In-cabin audio — consent-backed (signage), ideally event-triggered; handle
    Barbados Data Protection Act obligations deliberately.

Privacy note: continuous precise rider tracking is sensitive personal data —
build consent and a clear stated purpose (earning ride points) in from the start.

## Useful facts
- Barbados map center ≈ `13.19, -59.54`.
- Database: `psv_tracker`; DB user `psv_user`@`localhost`.
