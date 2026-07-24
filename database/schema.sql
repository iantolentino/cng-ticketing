-- Import into an empty cng_ticketing MySQL/MariaDB database, then run setup.php.
SET NAMES utf8mb4;

CREATE TABLE departments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(120) NOT NULL UNIQUE, code VARCHAR(40) NOT NULL UNIQUE, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE roles (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(80) NOT NULL UNIQUE, slug VARCHAR(80) NOT NULL UNIQUE, is_system TINYINT(1) NOT NULL DEFAULT 0, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE permissions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, permission_key VARCHAR(80) NOT NULL UNIQUE, label VARCHAR(120) NOT NULL, description VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE role_permissions (
  role_id BIGINT UNSIGNED NOT NULL, permission_id BIGINT UNSIGNED NOT NULL, granted TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY(role_id, permission_id),
  FOREIGN KEY(role_id) REFERENCES roles(id) ON DELETE CASCADE, FOREIGN KEY(permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, role_id BIGINT UNSIGNED NOT NULL, department_id BIGINT UNSIGNED NULL,
  username VARCHAR(80) NOT NULL UNIQUE, full_name VARCHAR(150) NOT NULL, email VARCHAR(190) NULL UNIQUE, password_hash VARCHAR(255) NOT NULL,
  must_change_password TINYINT(1) NOT NULL DEFAULT 1, is_active TINYINT(1) NOT NULL DEFAULT 1, last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_users_role_active(role_id, is_active), FOREIGN KEY(role_id) REFERENCES roles(id), FOREIGN KEY(department_id) REFERENCES departments(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE user_permission_overrides (
  user_id BIGINT UNSIGNED NOT NULL, permission_id BIGINT UNSIGNED NOT NULL, granted TINYINT(1) NOT NULL, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY(user_id, permission_id), FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE, FOREIGN KEY(permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE tickets (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, ticket_number VARCHAR(24) NOT NULL UNIQUE, issue_escalator VARCHAR(150) NOT NULL, subject VARCHAR(255) NOT NULL,
  category VARCHAR(80) NOT NULL, subcategory VARCHAR(80) NULL, department_id BIGINT UNSIGNED NOT NULL, employee_name VARCHAR(150) NOT NULL, description TEXT NOT NULL,
  status ENUM('open','in_progress','pending','closed') NOT NULL DEFAULT 'open', assignee_id BIGINT UNSIGNED NULL, created_by BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, closed_at DATETIME NULL,
  deleted_at DATETIME NULL, deleted_by BIGINT UNSIGNED NULL,
  KEY idx_tickets_dashboard(deleted_at,status,updated_at), KEY idx_tickets_department(department_id,deleted_at), KEY idx_tickets_assignee(assignee_id,deleted_at),
  FOREIGN KEY(department_id) REFERENCES departments(id), FOREIGN KEY(assignee_id) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY(created_by) REFERENCES users(id), FOREIGN KEY(deleted_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE ticket_comments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, ticket_id BIGINT UNSIGNED NOT NULL, user_id BIGINT UNSIGNED NOT NULL, body TEXT NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ticket_comments_ticket(ticket_id,created_at), FOREIGN KEY(ticket_id) REFERENCES tickets(id), FOREIGN KEY(user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE ticket_activity (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, ticket_id BIGINT UNSIGNED NOT NULL, actor_id BIGINT UNSIGNED NULL, action VARCHAR(80) NOT NULL, details JSON NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ticket_activity_ticket(ticket_id,created_at), FOREIGN KEY(ticket_id) REFERENCES tickets(id), FOREIGN KEY(actor_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Metadata hook only; attachment upload is intentionally deferred.
CREATE TABLE ticket_attachments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, ticket_id BIGINT UNSIGNED NOT NULL, uploaded_by BIGINT UNSIGNED NOT NULL,
  stored_name VARCHAR(255) NOT NULL, original_name VARCHAR(255) NOT NULL, mime_type VARCHAR(120) NOT NULL, file_size BIGINT UNSIGNED NOT NULL,
  access_permission_key VARCHAR(80) NOT NULL DEFAULT 'view_attachments', created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_attachments_ticket(ticket_id), FOREIGN KEY(ticket_id) REFERENCES tickets(id), FOREIGN KEY(uploaded_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE app_settings (setting_key VARCHAR(80) PRIMARY KEY, setting_value TEXT NOT NULL, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO departments(name,code) VALUES
('Repairs and Maintenance (R&M)','rm'),('Strata Customer Care','customer-care'),('Strata Admin Specialist','admin'),('Strata Insurance Specialist','insurance'),('Strata Compliance Specialist','compliance');
INSERT INTO roles(name,slug,is_system) VALUES
('Super Admin','super-admin',1),('Management','management',1),('Team Leader','team-leader',1),('Pod Leader','pod-leader',1),('Subject Matter Expert','sme',1),('Department Head','department-head',1);
INSERT INTO permissions(permission_key,label,description) VALUES
('view_all_tickets','View all tickets','See all active tickets and histories.'),('create_tickets','Create tickets','Create account tickets.'),('edit_tickets','Edit tickets','Update ticket fields and workflow status.'),('close_tickets','Close tickets','Set status to Closed.'),('comment_tickets','Comment on tickets','Add ticket comments.'),('assign_tickets','Assign tickets','Change the ticket assignee.'),('manage_users','Manage users','Create and manage users.'),('manage_roles','Manage roles and access','Change access grants.'),('delete_tickets','Delete tickets','Soft-delete tickets.'),('view_attachments','View attachments','View confidential attachments.'),('upload_attachments','Upload attachments','Upload confidential attachments.');

-- Permission grants: Team Leaders deliberately do not receive create/edit/close/assign.
INSERT INTO role_permissions(role_id,permission_id,granted) SELECT r.id,p.id,1 FROM roles r CROSS JOIN permissions p WHERE r.slug='super-admin';
INSERT INTO role_permissions(role_id,permission_id,granted) SELECT r.id,p.id,1 FROM roles r JOIN permissions p WHERE r.slug='management' AND p.permission_key IN ('view_all_tickets','create_tickets','edit_tickets','close_tickets','comment_tickets','assign_tickets');
INSERT INTO role_permissions(role_id,permission_id,granted) SELECT r.id,p.id,1 FROM roles r JOIN permissions p WHERE r.slug='department-head' AND p.permission_key IN ('view_all_tickets','edit_tickets','close_tickets','comment_tickets','assign_tickets');
INSERT INTO role_permissions(role_id,permission_id,granted) SELECT r.id,p.id,1 FROM roles r JOIN permissions p WHERE r.slug='team-leader' AND p.permission_key IN ('view_all_tickets','comment_tickets');
INSERT INTO role_permissions(role_id,permission_id,granted) SELECT r.id,p.id,1 FROM roles r JOIN permissions p WHERE r.slug='pod-leader' AND p.permission_key IN ('view_all_tickets','create_tickets','edit_tickets','close_tickets','comment_tickets','assign_tickets');
INSERT INTO role_permissions(role_id,permission_id,granted) SELECT r.id,p.id,1 FROM roles r JOIN permissions p WHERE r.slug='sme' AND p.permission_key IN ('view_all_tickets','comment_tickets');

