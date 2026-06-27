-- 015_speeding_events.sql — speeding events reported by the driver app.
-- One row per event (alarm fires -> open; alarm clears -> close). driver_id and
-- vehicle_id are captured server-side from the shift. speed_limit_kmh is stored
-- per-event so it reflects the limit in force at the time, independent of any
-- later change to the global setting. ended_at stays NULL if a close is dropped.

CREATE TABLE speeding_events (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  shift_id        BIGINT UNSIGNED NOT NULL,
  vehicle_id      INT UNSIGNED NOT NULL,
  driver_id       INT UNSIGNED NOT NULL,
  started_at      DATETIME NOT NULL,
  ended_at        DATETIME NULL,
  peak_speed_kmh  SMALLINT UNSIGNED NOT NULL,
  speed_limit_kmh SMALLINT UNSIGNED NOT NULL,
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_se_shift   FOREIGN KEY (shift_id)   REFERENCES shifts(id)   ON DELETE CASCADE,
  CONSTRAINT fk_se_vehicle FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE,
  CONSTRAINT fk_se_driver  FOREIGN KEY (driver_id)  REFERENCES drivers(id)  ON DELETE CASCADE,
  INDEX idx_se_shift (shift_id),
  INDEX idx_se_vehicle (vehicle_id)
) ENGINE=InnoDB;
