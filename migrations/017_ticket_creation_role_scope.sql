-- Additive migration only. Team Leaders and Super Admins are the default ticket creators.

INSERT INTO role_permissions(role_id, permission_id, granted)
SELECT r.id, p.id, 1
FROM roles r
JOIN permissions p ON p.permission_key = 'create_tickets'
WHERE r.slug IN ('super-admin', 'team-leader')
ON DUPLICATE KEY UPDATE granted = VALUES(granted);

INSERT INTO role_permissions(role_id, permission_id, granted)
SELECT r.id, p.id, 0
FROM roles r
JOIN permissions p ON p.permission_key = 'create_tickets'
WHERE r.slug NOT IN ('super-admin', 'team-leader')
ON DUPLICATE KEY UPDATE granted = VALUES(granted);
