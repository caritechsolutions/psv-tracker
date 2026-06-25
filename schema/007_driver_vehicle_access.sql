-- 007_driver_vehicle_access.sql — which vehicles each driver may sign on to.
-- Owners grant access between THEIR drivers and THEIR vehicles (enforced in the
-- owner portal); this table is the join. Both FKs cascade on delete.

CREATE TABLE driver_vehicle_access (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  driver_id  INT UNSIGNED NOT NULL,
  vehicle_id INT UNSIGNED NOT NULL,
  granted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_driver_vehicle (driver_id, vehicle_id),
  CONSTRAINT fk_dva_driver  FOREIGN KEY (driver_id)  REFERENCES drivers(id)  ON DELETE CASCADE,
  CONSTRAINT fk_dva_vehicle FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Seed continuity: grant the seeded test driver access to vehicle 1, but ONLY
-- if they already share a (non-null) owner. Both are ownerless today, so this
-- inserts nothing and the table is simply created — we never fabricate owner
-- links here. For the seed driver to gain access via the new endpoint, admin
-- must assign owners to the test driver and vehicle 1, then grant in the owner
-- portal (or this grant could be added by a later migration once owners exist).
INSERT INTO driver_vehicle_access (driver_id, vehicle_id)
SELECT d.id, v.id
  FROM drivers d JOIN vehicles v ON v.id = 1
 WHERE d.api_token = 'test-token-abc123'
   AND d.owner_id IS NOT NULL
   AND d.owner_id = v.owner_id;
