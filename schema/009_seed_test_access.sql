-- 009_seed_test_access.sql — sign-on now enforces driver_vehicle_access, so the
-- seeded test driver must hold a grant for vehicle 1 to keep working.
--
-- This inserts ONLY a row in the join table (an access grant). It does not set
-- owner_id on the driver or the vehicle, so no owner links are fabricated.
-- Idempotent: inserts only if the seed driver and vehicle 1 both exist and the
-- grant isn't already present (UNIQUE(driver_id, vehicle_id) is a second guard).

INSERT INTO driver_vehicle_access (driver_id, vehicle_id)
SELECT t.driver_id, 1
  FROM driver_tokens t
  JOIN vehicles v ON v.id = 1
 WHERE t.token = 'test-token-abc123'
   AND NOT EXISTS (
     SELECT 1 FROM driver_vehicle_access a
      WHERE a.driver_id = t.driver_id AND a.vehicle_id = 1
   );
