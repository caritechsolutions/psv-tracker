-- 013_rides.sql — rider check-ins (one verified ride = one trip point).
-- A ride ties a rider to a shift (and thus the vehicle/route/driver). Rider GPS
-- is used only to validate proximity at check-in and is NOT stored here.

CREATE TABLE rides (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  rider_id      INT UNSIGNED NOT NULL,
  shift_id      BIGINT UNSIGNED NOT NULL,
  vehicle_id    INT UNSIGNED NOT NULL,
  checked_in_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  status        ENUM('checked_in','void') NOT NULL DEFAULT 'checked_in',
  point_awarded TINYINT(1) NOT NULL DEFAULT 0,
  UNIQUE KEY uq_rider_shift (rider_id, shift_id),
  CONSTRAINT fk_ride_rider   FOREIGN KEY (rider_id)   REFERENCES riders(id)   ON DELETE CASCADE,
  CONSTRAINT fk_ride_shift   FOREIGN KEY (shift_id)   REFERENCES shifts(id)   ON DELETE CASCADE,
  CONSTRAINT fk_ride_vehicle FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE,
  INDEX idx_ride_rider (rider_id),
  INDEX idx_ride_vehicle (vehicle_id)
) ENGINE=InnoDB;
