-- Additive migration only. Safe to rerun; keeps legacy ticket columns for compatibility.

CREATE TABLE IF NOT EXISTS ticket_assignees (
  ticket_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (ticket_id, user_id),
  KEY idx_ticket_assignees_user (user_id, ticket_id),
  CONSTRAINT fk_ticket_assignees_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
  CONSTRAINT fk_ticket_assignees_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ticket_departments (
  ticket_id BIGINT UNSIGNED NOT NULL,
  department_id BIGINT UNSIGNED NOT NULL,
  added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (ticket_id, department_id),
  KEY idx_ticket_departments_department (department_id, ticket_id),
  CONSTRAINT fk_ticket_departments_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
  CONSTRAINT fk_ticket_departments_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO ticket_assignees(ticket_id, user_id)
SELECT id, assignee_id FROM tickets WHERE assignee_id IS NOT NULL;

INSERT IGNORE INTO ticket_departments(ticket_id, department_id)
SELECT id, department_id FROM tickets;

INSERT INTO roles(name, slug, is_system)
VALUES ('CNG Admin', 'cng-admin', 1)
ON DUPLICATE KEY UPDATE name = VALUES(name), is_system = VALUES(is_system);

INSERT INTO role_permissions(role_id, permission_id, granted)
SELECT r.id, p.id, 1
FROM roles r
JOIN permissions p
WHERE r.slug = 'cng-admin' AND p.permission_key = 'view_all_tickets'
ON DUPLICATE KEY UPDATE granted = VALUES(granted);
