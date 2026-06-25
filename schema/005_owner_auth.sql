-- 005_owner_auth.sql — give owners portal credentials (roadmap #6).
-- The owners table and vehicles.owner_id already exist (004). Both columns are
-- nullable so existing owners stay valid; an owner without credentials simply
-- can't log in to the portal. UNIQUE on username allows multiple NULLs.

ALTER TABLE owners
  ADD COLUMN username      VARCHAR(60)  NULL UNIQUE AFTER name,
  ADD COLUMN password_hash VARCHAR(255) NULL AFTER username;
