-- 014_ratings.sql — one rating per ride. A rating splits into a PUBLIC vehicle
-- score (shown on the map) and a PRIVATE driver score (owner/admin only). The
-- driver_id is captured from the ride's shift at rating time.

CREATE TABLE ratings (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ride_id       INT UNSIGNED NOT NULL UNIQUE,     -- one rating per ride
  rider_id      INT UNSIGNED NOT NULL,
  vehicle_id    INT UNSIGNED NOT NULL,
  driver_id     INT UNSIGNED NOT NULL,
  vehicle_stars TINYINT UNSIGNED NOT NULL,        -- 1..5
  driver_stars  TINYINT UNSIGNED NOT NULL,        -- 1..5
  comment       VARCHAR(500) NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_rating_ride    FOREIGN KEY (ride_id)    REFERENCES rides(id)    ON DELETE CASCADE,
  CONSTRAINT fk_rating_rider   FOREIGN KEY (rider_id)   REFERENCES riders(id)   ON DELETE CASCADE,
  CONSTRAINT fk_rating_vehicle FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE,
  CONSTRAINT fk_rating_driver  FOREIGN KEY (driver_id)  REFERENCES drivers(id)  ON DELETE CASCADE,
  INDEX idx_rating_vehicle (vehicle_id),
  INDEX idx_rating_driver (driver_id)
) ENGINE=InnoDB;
