-- 003_ads.sql — image banner ads for the admin portal (roadmap #4).
-- One global rotation; no targeting yet. Uploaded images live OUTSIDE the repo
-- at /var/lib/psv-tracker/uploads — this table stores the filename only.

CREATE TABLE ads (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title      VARCHAR(160) NOT NULL,                  -- admin label + banner alt text
  image_file VARCHAR(255) NOT NULL,                  -- stored filename only (no path)
  click_url  VARCHAR(2048) NULL,                     -- click-through target (nullable)
  weight     SMALLINT UNSIGNED NOT NULL DEFAULT 100, -- rotation sort order (ASC shows first)
  active     TINYINT(1) NOT NULL DEFAULT 1,
  starts_on  DATE NULL,                              -- campaign window start (nullable)
  ends_on    DATE NULL,                              -- campaign window end (nullable)
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_ads_active_weight (active, weight)
) ENGINE=InnoDB;
