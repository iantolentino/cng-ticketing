-- Additive migration for recruitment and employee scheduling details.

ALTER TABLE staff_directory
  ADD COLUMN hired_date DATE NULL AFTER email_domain,
  ADD COLUMN shift_schedule VARCHAR(160) NULL AFTER hired_date,
  ADD COLUMN is_in_training TINYINT(1) NOT NULL DEFAULT 0 AFTER shift_schedule,
  ADD COLUMN position_title VARCHAR(150) NULL AFTER is_in_training;

INSERT INTO permissions(permission_key,label,description)
VALUES ('manage_recruitment','Manage recruitment','View and edit employee recruitment details.')
ON DUPLICATE KEY UPDATE label=VALUES(label), description=VALUES(description);

INSERT INTO role_permissions(role_id,permission_id,granted)
SELECT r.id,p.id,1
FROM roles r
JOIN permissions p ON p.permission_key='manage_recruitment'
WHERE r.slug IN ('super-admin','management','team-leader','department-head','pod-leader')
ON DUPLICATE KEY UPDATE granted=VALUES(granted);
