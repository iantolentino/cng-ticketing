-- Account disable timestamps.
-- Additive migration; safe to apply more than once.
--
-- Why this exists:
--   * users.is_active could be turned off (Users page checkbox, Roles & Access toggle, pending
--     registration rejection) but nothing recorded WHEN it happened, so the Super Admin had no
--     "last disabled" date to show next to the Disabled status.
--   * updated_at moves for every unrelated edit, so it cannot stand in for the disable time.
--
-- Safety:
--   * The ADD COLUMN block is guarded by an INFORMATION_SCHEMA lookup, so re-running this file
--     is a no-op once the column exists.
--   * The backfill only touches accounts that are already inactive and have no recorded
--     disabled_at, so a real disable timestamp is never overwritten.

-- ---------------------------------------------------------------------------
-- 1. users.disabled_at
-- ---------------------------------------------------------------------------
SET @sql := (
  SELECT IF(COUNT(*) = 0,
    'ALTER TABLE users
       ADD COLUMN disabled_at DATETIME NULL AFTER is_active,
       ADD KEY idx_users_disabled_at (disabled_at)',
    'SELECT 1')
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'disabled_at'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------------
-- 2. Backfill: an account that is already inactive was disabled at its last update.
--    This is an approximation for pre-existing rows only; every future disable writes the
--    exact moment through user_active_state_update_sql().
-- ---------------------------------------------------------------------------
UPDATE users SET disabled_at = updated_at WHERE is_active = 0 AND disabled_at IS NULL;
