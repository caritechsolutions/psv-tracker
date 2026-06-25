-- 002_admin_users.sql — admin portal accounts (session-based login).
-- Applied once by schema/migrate.sh and recorded in schema_migrations.
-- No accounts are seeded here: create the first admin with the CLI helper
-- `php bin/create-admin.php` so no credentials ever live in the repo.

CREATE TABLE admin_users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(60)  NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  name          VARCHAR(120) NULL,
  status        ENUM('active','disabled') NOT NULL DEFAULT 'active',
  last_login_at TIMESTAMP NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
