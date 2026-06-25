-- 006_driver_owner.sql — drivers belong to an owner (owner-managed drivers).
-- Existing admin-created drivers keep a NULL owner, which is fine. Deleting an
-- owner detaches their drivers (SET NULL) rather than cascading.

ALTER TABLE drivers
  ADD COLUMN owner_id INT UNSIGNED NULL AFTER id,
  ADD CONSTRAINT fk_driver_owner FOREIGN KEY (owner_id)
      REFERENCES owners(id) ON DELETE SET NULL;
