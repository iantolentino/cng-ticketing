-- Additive migration only. Safe to rerun; aligns future scaffolding names and local test access.

CREATE TABLE IF NOT EXISTS company_holidays (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `date` DATE NOT NULL,
  label VARCHAR(150) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_company_holidays_date (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS leave_requests (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  employee_user_id BIGINT UNSIGNED NOT NULL,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  reason TEXT NOT NULL,
  status ENUM('pending','team_leader_approved','department_head_approved','rejected') NOT NULL DEFAULT 'pending',
  team_leader_approval_by BIGINT UNSIGNED NULL,
  team_leader_approval_at DATETIME NULL,
  department_head_approval_by BIGINT UNSIGNED NULL,
  department_head_approval_at DATETIME NULL,
  submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_leave_requests_employee_status (employee_user_id,status,start_date),
  KEY idx_leave_requests_status_dates (status,start_date,end_date),
  CONSTRAINT fk_leave_requests_employee FOREIGN KEY (employee_user_id) REFERENCES users(id),
  CONSTRAINT fk_leave_requests_team_leader FOREIGN KEY (team_leader_approval_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_leave_requests_department_head FOREIGN KEY (department_head_approval_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE ticket_attachments
  ADD COLUMN IF NOT EXISTS file_name VARCHAR(255) NULL AFTER uploaded_by,
  ADD COLUMN IF NOT EXISTS file_path VARCHAR(500) NULL AFTER file_name,
  ADD COLUMN IF NOT EXISTS uploaded_at DATETIME NULL AFTER file_path;

UPDATE ticket_attachments
SET file_name = COALESCE(file_name, original_name),
    file_path = COALESCE(file_path, stored_name),
    uploaded_at = COALESCE(uploaded_at, created_at);

ALTER TABLE ticket_attachments
  MODIFY file_name VARCHAR(255) NOT NULL,
  MODIFY file_path VARCHAR(500) NOT NULL,
  MODIFY uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;

INSERT INTO permissions(permission_key,label,description)
VALUES ('view_attachments','Can View Attachments','View confidential medical certificate attachments.')
ON DUPLICATE KEY UPDATE label=VALUES(label), description=VALUES(description);

INSERT INTO roles(name,slug,is_system)
VALUES ('Team Member','team-member',1)
ON DUPLICATE KEY UPDATE name=VALUES(name), is_system=VALUES(is_system);

INSERT INTO permissions(permission_key,label,description)
VALUES ('access_leave_request_module','Access leave request module','Use the restricted leave-request module.')
ON DUPLICATE KEY UPDATE label=VALUES(label), description=VALUES(description);

INSERT INTO role_permissions(role_id,permission_id,granted)
SELECT r.id,p.id,1
FROM roles r
JOIN permissions p
WHERE r.slug='team-member' AND p.permission_key='access_leave_request_module'
ON DUPLICATE KEY UPDATE granted=VALUES(granted);

INSERT INTO role_permissions(role_id,permission_id,granted)
SELECT r.id,p.id,1
FROM roles r
JOIN permissions p
WHERE r.slug IN ('team-leader','department-head') AND p.permission_key='access_leave_request_module'
ON DUPLICATE KEY UPDATE granted=VALUES(granted);
