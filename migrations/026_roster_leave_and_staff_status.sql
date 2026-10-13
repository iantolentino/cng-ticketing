-- Roster attendance rebuild, staff employment status, and staff-linked leave.
-- Additive migration; safe to apply once after migration 025.
--
-- Why this exists:
--   * staff_directory carried no department, no structured shift and no employment status,
--     so the Team Attendance page had nothing per-staff to show or edit.
--   * leave lived only in calendar_events with the employee name embedded in the title,
--     so Team Attendance and Team Calendar could not agree on who was on leave.
--   * leave_requests could only be filed for a users row, but most roster staff have no login.
--
-- Re-runnable: the ADD COLUMN blocks are guarded by INFORMATION_SCHEMA lookups and the
-- backfill only touches rows whose staff_id is still NULL.

-- ---------------------------------------------------------------------------
-- 1. staff_directory: department, team leader, structured shift, employment status
-- ---------------------------------------------------------------------------
SET @sql := (
  SELECT IF(COUNT(*) = 0,
    'ALTER TABLE staff_directory
       ADD COLUMN department_id BIGINT UNSIGNED NULL AFTER team_label,
       ADD COLUMN team_leader_id BIGINT UNSIGNED NULL AFTER department_id,
       ADD COLUMN shift_days VARCHAR(27) NULL AFTER shift_schedule,
       ADD COLUMN shift_start TIME NULL AFTER shift_days,
       ADD COLUMN shift_end TIME NULL AFTER shift_start,
       ADD COLUMN break_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 60 AFTER shift_end,
       ADD COLUMN employment_status VARCHAR(20) NOT NULL DEFAULT ''active'' AFTER is_active,
       ADD COLUMN status_effective_date DATE NULL AFTER employment_status,
       ADD COLUMN status_note VARCHAR(190) NULL AFTER status_effective_date,
       ADD COLUMN replacement_for_id BIGINT UNSIGNED NULL AFTER status_note,
       ADD KEY idx_staff_directory_department (department_id, employment_status, full_name),
       ADD KEY fk_staff_directory_team_leader (team_leader_id),
       ADD KEY fk_staff_directory_replacement (replacement_for_id),
       ADD CONSTRAINT fk_staff_directory_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
       ADD CONSTRAINT fk_staff_directory_team_leader FOREIGN KEY (team_leader_id) REFERENCES staff_directory(id) ON DELETE SET NULL,
       ADD CONSTRAINT fk_staff_directory_replacement FOREIGN KEY (replacement_for_id) REFERENCES staff_directory(id) ON DELETE SET NULL',
    'SELECT 1')
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'staff_directory' AND COLUMN_NAME = 'employment_status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------------
-- 2. calendar_events: first-class leave rows linked to the staff roster
-- ---------------------------------------------------------------------------
SET @sql := (
  SELECT IF(COUNT(*) = 0,
    'ALTER TABLE calendar_events
       ADD COLUMN staff_id BIGINT UNSIGNED NULL AFTER title,
       ADD COLUMN notes VARCHAR(255) NULL AFTER end_date,
       ADD COLUMN leave_type VARCHAR(40) NULL AFTER notes,
       ADD KEY idx_calendar_events_staff (staff_id, event_date),
       ADD UNIQUE KEY uq_calendar_events_staff_leave_start (staff_id, event_date, leave_type),
       ADD CONSTRAINT fk_calendar_events_staff FOREIGN KEY (staff_id) REFERENCES staff_directory(id) ON DELETE CASCADE',
    'SELECT 1')
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'calendar_events' AND COLUMN_NAME = 'staff_id'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- event_type gains 'leave' so leave rows are identifiable without parsing the title.
ALTER TABLE calendar_events
  MODIFY event_type ENUM('team_event','coverage','reminder','other','leave') NOT NULL DEFAULT 'team_event';

-- ---------------------------------------------------------------------------
-- 3. leave_requests: allow filing on behalf of a roster staff member
-- ---------------------------------------------------------------------------
ALTER TABLE leave_requests
  MODIFY employee_user_id BIGINT UNSIGNED NULL;

SET @sql := (
  SELECT IF(COUNT(*) = 0,
    'ALTER TABLE leave_requests
       ADD COLUMN staff_id BIGINT UNSIGNED NULL AFTER employee_user_id,
       ADD COLUMN created_by BIGINT UNSIGNED NULL AFTER staff_id,
       ADD COLUMN leave_type VARCHAR(40) NOT NULL DEFAULT ''annual'' AFTER end_date,
       ADD KEY idx_leave_requests_staff (staff_id, start_date, end_date),
       ADD KEY fk_leave_requests_created_by (created_by),
       ADD CONSTRAINT fk_leave_requests_staff FOREIGN KEY (staff_id) REFERENCES staff_directory(id) ON DELETE SET NULL,
       ADD CONSTRAINT fk_leave_requests_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL',
    'SELECT 1')
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'leave_requests' AND COLUMN_NAME = 'staff_id'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------------
-- 4. Backfill: link the existing title-based leave events to the staff roster.
--    Matching is exact ("<full name> - <leave type> Leave") so a partial name can
--    never bind a leave record to the wrong person. Titles are left untouched, so
--    every existing calendar entry keeps the text it has on production today.
-- ---------------------------------------------------------------------------
UPDATE calendar_events ce
JOIN staff_directory sd ON ce.title = CONCAT(sd.full_name, ' - Annual Leave')
SET ce.staff_id = sd.id, ce.event_type = 'leave', ce.leave_type = 'annual'
WHERE ce.staff_id IS NULL;

UPDATE calendar_events ce
JOIN staff_directory sd ON ce.title = CONCAT(sd.full_name, ' - Half Day Leave')
SET ce.staff_id = sd.id, ce.event_type = 'leave', ce.leave_type = 'half_day'
WHERE ce.staff_id IS NULL;
