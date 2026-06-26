-- 010_revoke_test_token.sql — revoke the publicly-known test token.
-- test-token-abc123 ships in the repo/CLAUDE.md, so on an internet-facing server
-- anyone could authenticate with it. Delete just the token row; the Test Driver
-- account and its driver_vehicle_access grants are left intact. After this the
-- token returns 401 everywhere. Idempotent (no-op if already gone).
--
-- Real testing no longer uses this token: obtain a private token via
-- api/driver-login.php (e.g. driver "rrrrr", who has a password and a grant).

DELETE FROM driver_tokens WHERE token = 'test-token-abc123';
