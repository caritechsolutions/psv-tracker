-- 008_settings.sql — generic key/value settings, so new global settings need no
-- schema change. First setting: the global speed limit (km/h) for the driver app.

CREATE TABLE settings (
  setting_key   VARCHAR(60) PRIMARY KEY,
  setting_value TEXT NOT NULL,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO settings (setting_key, setting_value) VALUES ('speed_limit_kmh', '60');
