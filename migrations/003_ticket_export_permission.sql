-- Additive migration only. Safe to rerun; does not modify ticket data or existing grants.

INSERT INTO permissions(permission_key,label,description)
VALUES ('export_tickets','Can Export Tickets','Export the visible ticket list as CSV or Excel.')
ON DUPLICATE KEY UPDATE permission_key=permission_key;

INSERT INTO role_permissions(role_id,permission_id,granted)
SELECT r.id,p.id,1
FROM roles r
JOIN permissions p
WHERE r.slug='super-admin' AND p.permission_key='export_tickets'
ON DUPLICATE KEY UPDATE granted=granted;
