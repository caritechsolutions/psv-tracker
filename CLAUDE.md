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
  `positions`; seeds a test driver (token `test-token-abc123`), vehicle id 1,
  route id 1.
- `install.sh`, `update.sh`, `nginx/psv-tracker.conf`.

## How to work in this repo
- Ask clarifying questions before guessing. Propose the approach/architecture
  first and wait for an OK before writing code.
- Build in small steps; let me confirm each one works before moving to the next.
  No scope creep beyond the current step.
- Don't touch the capture `api/` or any already-applied migration unless asked.

## Roadmap

Done:
1. Capture API — driver sign-on / ping / sign-off, bearer-token auth.
2. Admin portal — login + live Barbados map of signed-on vehicles.

Current:
3. Admin shell — shared layout: left nav rail (Map, Ads, Fleet, Owners,
   Reports, Settings) + one header/layout partial. Retrofit the map into it;
   map controls become a collapsible overlay panel (live vehicle list,
   route/seat filters, search) over a full-bleed map.

Next:
4. Ads configuration — first real section built inside the shell.
5. Fleet management — vehicles (with a capacity field), drivers, routes. The
   driver/conductor seat-status toggle is the source of truth for
   seats-available.
6. Owner portal — owners see only their own vehicles and telemetry (speed,
   position).

Rider side (separate workstream, after the admin portal):
7. Rider app/web — live view + ETA for riders.
8. Ride rewards — distance-travelled points for retention. Compute distance
   from the rider's own trip pings, confirmed as a real PSV ride via
   co-movement matching (rider speed/heading/stop-go tracks a tracked vehicle).
   Server-side anti-fraud: mock-location/GPS-spoof detection, route/speed
   plausibility, rate caps. Battery-friendly: track only during an active trip,
   foreground-service notification, sensible cadence.
9. GPS occupancy (later) — infer seats-available from co-moving rider devices;
   only viable once app penetration is high (undercounts otherwise). Automatic
   passenger counters (door sensors) are the accurate-count upgrade when the
   per-vehicle hardware cost is justified.

Later:
10. In-cabin audio — consent-backed (signage), ideally event-triggered; handle
    Barbados Data Protection Act obligations deliberately.

Privacy note: continuous precise rider tracking is sensitive personal data —
build consent and a clear stated purpose (earning ride points) in from the start.

## Useful facts
- Barbados map center ≈ `13.19, -59.54`.
- Database: `psv_tracker`; DB user `psv_user`@`localhost`.
