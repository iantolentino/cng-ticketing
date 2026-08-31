-- Additive migration. Grants Team Members the ticket workspace while the
-- application scopes their native tickets to rows they created.

INSERT INTO role_permissions(role_id, permission_id, granted)
SELECT r.id, p.id, 1
FROM roles r
JOIN permissions p ON p.permission_key = 'view_all_tickets'
WHERE r.slug = 'team-member'
ON DUPLICATE KEY UPDATE granted = VALUES(granted);

INSERT INTO role_permissions(role_id, permission_id, granted)
SELECT r.id, p.id, 1
FROM roles r
JOIN permissions p ON p.permission_key = 'create_tickets'
WHERE r.slug = 'team-member'
ON DUPLICATE KEY UPDATE granted = VALUES(granted);
