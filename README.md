# PSV Tracker

Server for tracking public service vehicles in Barbados. Drivers run an Android
app that signs on to a route and streams GPS pings; this server captures that
data and (in later steps) serves an admin map portal and per-owner dashboards.

## Install (fresh Ubuntu server)

```bash
curl -sSL "https://raw.githubusercontent.com/caritechsolutions/psv-tracker/main/install.sh?v=$(date +%s)" | sudo bash
```

This installs nginx, php-fpm, and MariaDB; clones the repo to `/opt/psv-tracker`;
creates the `psv_tracker` database with a generated password (written to
`api/config.php`, which is git-ignored); applies the schema migrations; and
brings the capture API up on port 80.

## Update (after new code is pushed)

```bash
sudo /opt/psv-tracker/update.sh
```

Pulls the latest code and applies any new migrations.

## Test the capture API

```bash
# sign on -> returns a shift_id
curl -X POST http://localhost/api/signon.php \
  -H "Authorization: Bearer test-token-abc123" \
  -d '{"vehicle_id":1,"route_id":1}'

# ping (use the shift_id returned above)
curl -X POST http://localhost/api/ping.php \
  -H "Authorization: Bearer test-token-abc123" \
  -d '{"shift_id":1,"lat":13.0975,"lng":-59.6189,"speed":30,"heading":270,"seat_status":"available"}'

# sign off
curl -X POST http://localhost/api/signoff.php \
  -H "Authorization: Bearer test-token-abc123" \
  -d '{"shift_id":1}'
```

## Layout

```
api/        capture endpoints (signon, ping, signoff) + shared db helper
schema/     numbered SQL migrations + migrate.sh runner
nginx/      site config template
install.sh  one-shot installer (curl | sudo bash)
update.sh   git pull + migrate
```
