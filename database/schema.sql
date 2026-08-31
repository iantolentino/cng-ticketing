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
  must_change_password TINYINT(1) NOT NULL DEFAULT 1, is_active TINYINT(1) NOT NULL DEFAULT 1, approval_status ENUM('approved','pending','rejected') NOT NULL DEFAULT 'approved', last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_users_role_active(role_id, is_active), FOREIGN KEY(role_id) REFERENCES roles(id), FOREIGN KEY(department_id) REFERENCES departments(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE user_permission_overrides (
  user_id BIGINT UNSIGNED NOT NULL, permission_id BIGINT UNSIGNED NOT NULL, granted TINYINT(1) NOT NULL, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY(user_id, permission_id), FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE, FOREIGN KEY(permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE notifications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL, actor_id BIGINT UNSIGNED NULL,
  type VARCHAR(60) NOT NULL, title VARCHAR(160) NOT NULL, body VARCHAR(500) NOT NULL, url VARCHAR(500) NOT NULL,
  read_at DATETIME NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_notifications_user_read(user_id, read_at, created_at), KEY idx_notifications_user_created(user_id, created_at),
  FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE, FOREIGN KEY(actor_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE tickets (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, ticket_number VARCHAR(24) NOT NULL UNIQUE, issue_escalator VARCHAR(150) NOT NULL, subject VARCHAR(255) NOT NULL,
  category VARCHAR(80) NOT NULL, subcategory VARCHAR(80) NULL, department_id BIGINT UNSIGNED NOT NULL, employee_name VARCHAR(150) NOT NULL, description TEXT NOT NULL, issue TEXT NULL, resolution TEXT NULL,
  status ENUM('open','in_progress','pending','closed') NOT NULL DEFAULT 'open', priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal', assignee_id BIGINT UNSIGNED NULL, created_by BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, closed_at DATETIME NULL,
  deleted_at DATETIME NULL, deleted_by BIGINT UNSIGNED NULL,
  KEY idx_tickets_dashboard(deleted_at,status,updated_at), KEY idx_tickets_department(department_id,deleted_at), KEY idx_tickets_assignee(assignee_id,deleted_at),
  FOREIGN KEY(department_id) REFERENCES departments(id), FOREIGN KEY(assignee_id) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY(created_by) REFERENCES users(id), FOREIGN KEY(deleted_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE ticket_assignees (
  ticket_id BIGINT UNSIGNED NOT NULL, user_id BIGINT UNSIGNED NOT NULL, assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY(ticket_id, user_id), KEY idx_ticket_assignees_user(user_id, ticket_id),
  FOREIGN KEY(ticket_id) REFERENCES tickets(id) ON DELETE CASCADE, FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE ticket_departments (
  ticket_id BIGINT UNSIGNED NOT NULL, department_id BIGINT UNSIGNED NOT NULL, added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY(ticket_id, department_id), KEY idx_ticket_departments_department(department_id, ticket_id),
  FOREIGN KEY(ticket_id) REFERENCES tickets(id) ON DELETE CASCADE, FOREIGN KEY(department_id) REFERENCES departments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE ticket_comments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, ticket_id BIGINT UNSIGNED NOT NULL, user_id BIGINT UNSIGNED NOT NULL, body TEXT NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ticket_comments_ticket(ticket_id,created_at), FOREIGN KEY(ticket_id) REFERENCES tickets(id), FOREIGN KEY(user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE ticket_activity (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, ticket_id BIGINT UNSIGNED NOT NULL, actor_id BIGINT UNSIGNED NULL, action VARCHAR(80) NOT NULL, details JSON NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ticket_activity_ticket(ticket_id,created_at), FOREIGN KEY(ticket_id) REFERENCES tickets(id), FOREIGN KEY(actor_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE api_tokens (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, token CHAR(64) NULL, token_hash CHAR(64) NOT NULL UNIQUE, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, created_by BIGINT UNSIGNED NULL, revoked_at DATETIME NULL, KEY idx_api_tokens_active(revoked_at,token_hash), FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE api_feed_access_log (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, token_id BIGINT UNSIGNED NULL, ip_address VARCHAR(45) NOT NULL, query_params JSON NULL, status_code SMALLINT UNSIGNED NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY idx_api_feed_access_log_created(created_at), FOREIGN KEY(token_id) REFERENCES api_tokens(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE ticket_activity_log (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, ticket_id BIGINT UNSIGNED NOT NULL, action VARCHAR(80) NOT NULL, changed_fields JSON NULL, old_values JSON NULL, new_values JSON NULL, changed_by VARCHAR(100) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY idx_ticket_activity_log_ticket(ticket_id,created_at), FOREIGN KEY(ticket_id) REFERENCES tickets(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Metadata hook only; attachment upload is intentionally deferred.
CREATE TABLE ticket_attachments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, ticket_id BIGINT UNSIGNED NOT NULL, uploaded_by BIGINT UNSIGNED NOT NULL,
  file_name VARCHAR(255) NOT NULL, file_path VARCHAR(500) NOT NULL, uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  stored_name VARCHAR(255) NOT NULL, original_name VARCHAR(255) NOT NULL, mime_type VARCHAR(120) NOT NULL, file_size BIGINT UNSIGNED NOT NULL,
  access_permission_key VARCHAR(80) NOT NULL DEFAULT 'view_attachments', created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_attachments_ticket(ticket_id), FOREIGN KEY(ticket_id) REFERENCES tickets(id), FOREIGN KEY(uploaded_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE company_holidays (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, `date` DATE NOT NULL, label VARCHAR(150) NOT NULL,
  country_code VARCHAR(12) NOT NULL DEFAULT 'COMPANY', holiday_type VARCHAR(40) NOT NULL DEFAULT 'company',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_company_holidays_scope(`date`,country_code,label)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE calendar_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, title VARCHAR(180) NOT NULL, event_date DATE NOT NULL, end_date DATE NULL,
  event_type ENUM('team_event','coverage','reminder','other') NOT NULL DEFAULT 'team_event', created_by BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY idx_calendar_events_dates(event_date,end_date),
  FOREIGN KEY(created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE leave_requests (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, employee_user_id BIGINT UNSIGNED NOT NULL,
  start_date DATE NOT NULL, end_date DATE NOT NULL, reason TEXT NOT NULL,
  status ENUM('pending','team_leader_approved','department_head_approved','rejected') NOT NULL DEFAULT 'pending',
  team_leader_approval_by BIGINT UNSIGNED NULL, team_leader_approval_at DATETIME NULL,
  department_head_approval_by BIGINT UNSIGNED NULL, department_head_approval_at DATETIME NULL,
  submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_leave_requests_employee_status(employee_user_id,status,start_date), KEY idx_leave_requests_status_dates(status,start_date,end_date),
  FOREIGN KEY(employee_user_id) REFERENCES users(id), FOREIGN KEY(team_leader_approval_by) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY(department_head_approval_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE leave_request_attachments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, leave_request_id BIGINT UNSIGNED NOT NULL, uploaded_by BIGINT UNSIGNED NOT NULL,
  file_name VARCHAR(255) NOT NULL, file_path VARCHAR(500) NOT NULL, mime_type VARCHAR(120) NOT NULL, file_size BIGINT UNSIGNED NOT NULL,
  uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY idx_leave_request_attachments_request(leave_request_id),
  FOREIGN KEY(leave_request_id) REFERENCES leave_requests(id) ON DELETE CASCADE, FOREIGN KEY(uploaded_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE team_attendance (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, attendance_date DATE NOT NULL, department_id BIGINT UNSIGNED NOT NULL, logged_by BIGINT UNSIGNED NOT NULL,
  status VARCHAR(40) NOT NULL DEFAULT 'annual', headcount INT UNSIGNED NOT NULL DEFAULT 0, notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_team_attendance_day_department(attendance_date, department_id), KEY idx_team_attendance_date(attendance_date,status),
  FOREIGN KEY(department_id) REFERENCES departments(id) ON DELETE CASCADE, FOREIGN KEY(logged_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE staff_directory (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, full_name VARCHAR(150) NOT NULL UNIQUE, team_label VARCHAR(120) NOT NULL,
  email_domain VARCHAR(190) NULL, is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_staff_directory_active_team(is_active,team_label,full_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE team_attendance_leave (
  attendance_id BIGINT UNSIGNED NOT NULL, staff_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY(attendance_id,staff_id), KEY idx_team_attendance_leave_staff(staff_id,attendance_id),
  FOREIGN KEY(attendance_id) REFERENCES team_attendance(id) ON DELETE CASCADE,
  FOREIGN KEY(staff_id) REFERENCES staff_directory(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO staff_directory(full_name,team_label,email_domain) VALUES
('Cyril Anne Bayaya','SME','jamesons.com.au'),('Paul Henzon','Admin','jamesons.com.au'),('Caissa Kiana Peña','Admin','jamesons.com.au'),('Princess Fionna Cunanan','Admin','jamesons.com.au'),('Mellen Roxas','Admin','jamesons.com.au'),('Regie Manipon','Admin','jamesons.com.au'),('Kaycee Soriano','Admin','jamesons.com.au'),('Jim Lansangan','Admin','jamesons.com.au'),
('Prince Andrew Mangahas','Compliance','jamesons.com.au'),('Abijah Dela Cruz','Compliance','jamesons.com.au'),('Kelly Vital','Compliance','jamesons.com.au'),('William Wallace Dizon','Compliance','jamesons.com.au'),('Byran Dale Bernardo','Compliance','jamesons.com.au'),('Harold Garcia','Compliance','jamesons.com.au'),('Arnn Canlas','Compliance','jamesons.com.au'),('Maria Daniella Cayanan','Compliance','jamesons.com.au'),
('Mary Angeline Dela Cruz','Insurance','jamesons.com.au'),('Katherine Advincula','Insurance','jamesons.com.au'),('Thea Janelle Waje','Insurance','jamesons.com.au'),('Shaniah Dantes','Strata Care','jamesons.com.au'),('Reynajoy Dacquel','Strata Care','jamesons.com.au'),('Arman Estrada','Strata Care','jamesons.com.au'),('Yansen Gray Mendoza','Strata Care','jamesons.com.au'),('Ylla Legion','Strata Care','jamesons.com.au'),
('Mark Anthony Lorenzo','Strata Care','jamesons.com.au'),('Bryan Nucup','Strata Care','jamesons.com.au'),('Allen Pascua','Strata Care','jamesons.com.au'),('Helga Rizza Bumagat','Strata Care','jamesons.com.au'),('Jolianna Manguerra','Strata Care','jamesons.com.au'),('Jay Anne Cohlene Sanchez','Strata Care','jamesons.com.au'),('Kimberly Rose Ramos','Strata Care','jamesons.com.au'),('Shan Keiki Galapon','Strata Care','jamesons.com.au'),
('Jamie Tapnio','Strata Care','jamesons.com.au'),('Floyd Sanchez','Strata Care','jamesons.com.au'),('Arhelo Mesina','Strata Care','jamesons.com.au'),('Keannu Sicat','Strata Care','jamesons.com.au'),('Trisha Mae Pineda','Strata Care','jamesons.com.au'),('John Michael Orcilino','Strata Care','jamesons.com.au'),('Divina Rabogo','CC Lead','jamesons.com.au'),('Danmark Tabing','Customer Care','jamesons.com.au'),
('Crisden Jacob Sarate','Customer Care','jamesons.com.au'),('Maria Carmel Requieron','Customer Care','jamesons.com.au'),('Michael Allan Chester Mon','Customer Care','jamesons.com.au'),('Reyby Arceo','Customer Care','jamesons.com.au'),('Mark Diaz','Customer Care','jamesons.com.au'),('Mark Anthony Labonete','Customer Care','jamesons.com.au'),('Arnielyn Garcia','Customer Care','jamesons.com.au'),('Paulyn Lino','R&M Lead','jamesons.com.au'),
('Rhon Romano','R&M Lead','jamesons.com.au'),('John Henry Casingal','R&M Lead','jamesons.com.au'),('Catherine Ronquillo','R&M','jamesons.com.au'),('Carlyn Dizon','R&M','jamesons.com.au'),('April Basilio','R&M','jamesons.com.au'),('Lorden Jay Magsino','R&M','jamesons.com.au'),('Rai Daniel Oliva','R&M','jamesons.com.au'),('Crizian Lloyd Cabrera','R&M','jamesons.com.au'),
('Marcela Carambas','R&M','jamesons.com.au'),('Alexis Sandra Garcia','R&M','jamesons.com.au'),('Marjerie Santos','R&M','jamesons.com.au'),('Elvin Sarmiento','R&M','jamesons.com.au'),('Janella Fermin','R&M','jamesons.com.au'),('Analyn Alcovendas','R&M','jamesons.com.au'),('Princes Rociane Laxamana','R&M','jamesons.com.au'),('Maricris Abia','R&M','jamesons.com.au'),
('Nico Cristobal','R&M Mid','jamesons.com.au'),('Clarisse Fernandez','R&M Mid','jamesons.com.au'),('Stefanie Estandian','R&M GY','jamesons.com.au'),('Angelica Sampana','R&M GY','jamesons.com.au'),('Joan Annaliza Tapnio','R&M GY','jamesons.com.au'),('Carl Sundian','R&M GY','jamesons.com.au'),('Leonard Sunga','TL','stratastaffglobal.com'),('Sheena Magdaraog','TL','stratastaffglobal.com'),('Trisha Balingit','TL','stratastaffglobal.com');

CREATE TABLE app_settings (setting_key VARCHAR(80) PRIMARY KEY, setting_value TEXT NOT NULL, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO departments(name,code) VALUES
('Repairs and Maintenance (R&M)','rm'),('Strata Customer Care','customer-care'),('Strata Admin Specialist','admin'),('Strata Insurance Specialist','insurance'),('Strata Compliance Specialist','compliance');
INSERT INTO roles(name,slug,is_system) VALUES
('Super Admin','super-admin',1),('Management','management',1),('Team Leader','team-leader',1),('Pod Leader','pod-leader',1),('Subject Matter Expert','sme',1),('Department Head','department-head',1),('CNG Admin','cng-admin',1),('Team Member','team-member',1);
INSERT INTO permissions(permission_key,label,description) VALUES
('view_all_tickets','View all tickets','See all active tickets and histories.'),('create_tickets','Create tickets','Create account tickets.'),('edit_tickets','Edit tickets','Update ticket fields and workflow status.'),('close_tickets','Close tickets','Set status to Closed.'),('comment_tickets','Comment on tickets','Add ticket comments.'),('assign_tickets','Assign tickets','Change the ticket assignee.'),('manage_users','Manage users','Create and manage users.'),('manage_roles','Manage roles and access','Change access grants.'),('delete_tickets','Delete tickets','Soft-delete tickets.'),('view_attachments','Can View Attachments','View confidential medical certificate attachments.'),('upload_attachments','Upload attachments','Upload confidential attachments.'),('access_leave_request_module','Access leave request module','Use the restricted leave-request module.');

-- Permission grants: Team Leaders deliberately do not receive create/edit/close/assign.
INSERT INTO role_permissions(role_id,permission_id,granted) SELECT r.id,p.id,1 FROM roles r CROSS JOIN permissions p WHERE r.slug='super-admin';
INSERT INTO role_permissions(role_id,permission_id,granted) SELECT r.id,p.id,1 FROM roles r JOIN permissions p WHERE r.slug='management' AND p.permission_key IN ('view_all_tickets','edit_tickets','close_tickets','comment_tickets','assign_tickets');
INSERT INTO role_permissions(role_id,permission_id,granted) SELECT r.id,p.id,1 FROM roles r JOIN permissions p WHERE r.slug='department-head' AND p.permission_key IN ('view_all_tickets','edit_tickets','close_tickets','comment_tickets','assign_tickets');
INSERT INTO role_permissions(role_id,permission_id,granted) SELECT r.id,p.id,1 FROM roles r JOIN permissions p WHERE r.slug='team-leader' AND p.permission_key IN ('view_all_tickets','create_tickets','comment_tickets');
INSERT INTO role_permissions(role_id,permission_id,granted) SELECT r.id,p.id,1 FROM roles r JOIN permissions p WHERE r.slug='pod-leader' AND p.permission_key IN ('view_all_tickets','edit_tickets','close_tickets','comment_tickets','assign_tickets');
INSERT INTO role_permissions(role_id,permission_id,granted) SELECT r.id,p.id,1 FROM roles r JOIN permissions p WHERE r.slug='sme' AND p.permission_key IN ('view_all_tickets','comment_tickets');
INSERT INTO role_permissions(role_id,permission_id,granted) SELECT r.id,p.id,1 FROM roles r JOIN permissions p WHERE r.slug='cng-admin' AND p.permission_key IN ('view_all_tickets');
INSERT INTO role_permissions(role_id,permission_id,granted) SELECT r.id,p.id,1 FROM roles r JOIN permissions p WHERE r.slug='team-member' AND p.permission_key IN ('view_all_tickets','create_tickets','access_leave_request_module');
INSERT INTO role_permissions(role_id,permission_id,granted) SELECT r.id,p.id,1 FROM roles r JOIN permissions p WHERE r.slug IN ('team-leader','department-head') AND p.permission_key IN ('access_leave_request_module');
