-- 011_auth_throttle.sql — DB-backed brute-force limiter for the login endpoints.
-- One row per bucket ("<scope>|<client_ip>"), upserted on each failed attempt;
-- successful login deletes the bucket. See api/rate_limit.php for the logic.

CREATE TABLE auth_throttle (
  bucket          VARCHAR(160) PRIMARY KEY,
  fail_count      INT UNSIGNED NOT NULL DEFAULT 0,
  first_failed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_failed_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
