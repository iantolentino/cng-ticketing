-- Additive migration: allow CNG Admin users to use capabilities explicitly selected for their role.

INSERT INTO permissions(permission_key,label,description)
VALUES
    ('export_tickets','Can Export Tickets','Export the visible ticket list as CSV or Excel.'),
    ('manage_recruitment','Manage recruitment','View and edit employee recruitment details.')
ON DUPLICATE KEY UPDATE label=VALUES(label), description=VALUES(description);

INSERT INTO role_permissions(role_id,permission_id,granted)
SELECT r.id,p.id,1
FROM roles r
JOIN permissions p ON p.permission_key IN ('export_tickets','manage_recruitment')
WHERE r.slug='cng-admin'
ON DUPLICATE KEY UPDATE granted=granted;
