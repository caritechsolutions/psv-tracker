-- 004_fleet.sql — fleet management (roadmap #5): owners, vehicle↔owner link,
-- and revocable driver tokens. Also migrates capture auth from drivers.api_token
-- to the driver_tokens table.
--
-- Backward compatibility: the seeded test token (test-token-abc123) is copied
-- into driver_tokens below, so the capture API and all existing test commands
-- keep working after authenticate_driver() switches to the token table.

-- Owners (table now; the Owners portal itself is roadmap #6).
CREATE TABLE owners (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name         VARCHAR(160) NOT NULL,
  contact_name VARCHAR(160) NULL,
  email        VARCHAR(190) NULL,
  phone        VARCHAR(40)  NULL,
  status       ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Vehicles gain a nullable owner. Nullable is required: the seeded vehicle has
-- no owner and must stay valid. Deleting an owner detaches, never cascades.
ALTER TABLE vehicles
  ADD COLUMN owner_id INT UNSIGNED NULL AFTER id,
  ADD CONSTRAINT fk_vehicle_owner FOREIGN KEY (owner_id)
      REFERENCES owners(id) ON DELETE SET NULL;

-- Issued, individually-revocable driver tokens, decoupled from the account.
-- Login inserts a row; logout/revoke deletes one. Disabling a driver disables
-- all their tokens via the status check in authenticate_driver().
CREATE TABLE driver_tokens (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  driver_id    INT UNSIGNED NOT NULL,
  token        VARCHAR(64)  NOT NULL UNIQUE,
  label        VARCHAR(120) NULL,
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_used_at TIMESTAMP NULL DEFAULT NULL,
  CONSTRAINT fk_token_driver FOREIGN KEY (driver_id)
      REFERENCES drivers(id) ON DELETE CASCADE,
  INDEX idx_token_driver (driver_id)
) ENGINE=InnoDB;

-- api_token is no longer the auth key. Make it nullable so admin-created drivers
-- (who authenticate via issued tokens) don't need a placeholder value. The
-- UNIQUE index stays; MySQL allows multiple NULLs under it.
ALTER TABLE drivers MODIFY api_token VARCHAR(64) NULL;

-- CRITICAL backward-compat seed: keep the existing test token authenticating.
INSERT INTO driver_tokens (driver_id, token, label)
SELECT id, 'test-token-abc123', 'seed test token'
FROM drivers WHERE api_token = 'test-token-abc123';
