-- 001_init.sql — core capture tables + seed data.
-- The migrations runner applies this once and records it in schema_migrations.
-- Database creation and the DB user are handled by install.sh.

CREATE TABLE drivers (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(120) NOT NULL,
  username      VARCHAR(60)  NOT NULL UNIQUE,
  password_hash VARCHAR(255) NULL,
  api_token     VARCHAR(64)  NOT NULL UNIQUE,
  status        ENUM('active','suspended') NOT NULL DEFAULT 'active',
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE vehicles (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  registration VARCHAR(20) NOT NULL UNIQUE,
  label        VARCHAR(80) NULL,
  capacity     SMALLINT UNSIGNED NULL,
  status       ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE routes (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  route_number VARCHAR(20)  NOT NULL,
  name         VARCHAR(160) NOT NULL,
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE shifts (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  driver_id  INT UNSIGNED NOT NULL,
  vehicle_id INT UNSIGNED NOT NULL,
  route_id   INT UNSIGNED NOT NULL,
  status     ENUM('open','closed') NOT NULL DEFAULT 'open',
  started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ended_at   TIMESTAMP NULL,
  CONSTRAINT fk_shift_driver  FOREIGN KEY (driver_id)  REFERENCES drivers(id),
  CONSTRAINT fk_shift_vehicle FOREIGN KEY (vehicle_id) REFERENCES vehicles(id),
  CONSTRAINT fk_shift_route   FOREIGN KEY (route_id)   REFERENCES routes(id),
  INDEX idx_shift_driver_status (driver_id, status)
) ENGINE=InnoDB;

CREATE TABLE positions (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  shift_id    BIGINT UNSIGNED NOT NULL,
  lat         DECIMAL(9,6) NOT NULL,
  lng         DECIMAL(9,6) NOT NULL,
  speed       DECIMAL(6,2) NULL,
  heading     SMALLINT UNSIGNED NULL,
  seat_status ENUM('available','full','unknown') NOT NULL DEFAULT 'unknown',
  recorded_at DATETIME NOT NULL,
  received_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_pos_shift FOREIGN KEY (shift_id) REFERENCES shifts(id),
  INDEX idx_pos_shift_time (shift_id, recorded_at),
  INDEX idx_pos_recorded   (recorded_at)
) ENGINE=InnoDB;

-- Seed data for testing
INSERT INTO routes (route_number, name) VALUES ('1', 'Bridgetown - Speightstown');
INSERT INTO vehicles (registration, label, capacity) VALUES ('ZR-1234', 'Test ZR', 14);
INSERT INTO drivers (name, username, api_token) VALUES ('Test Driver', 'testdriver', 'test-token-abc123');
