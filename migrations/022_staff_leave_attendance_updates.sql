-- Attendance corrections and staff leave checklist.
-- Additive migration; safe to apply once after migration 021.

ALTER TABLE team_attendance
  MODIFY status VARCHAR(40) NOT NULL DEFAULT 'annual';

UPDATE tickets t
JOIN users u ON u.id = t.created_by
SET t.issue_escalator = u.full_name
WHERE TRIM(t.issue_escalator) = '';

UPDATE users u
JOIN roles r ON r.id = u.role_id
SET u.email = CASE u.full_name
  WHEN 'Leonard Sunga' THEN 'leonard@stratastaffglobal.com'
  WHEN 'Sheena Magdaraog' THEN 'sheena@stratastaffglobal.com'
  WHEN 'Trisha Balingit' THEN 'trisha@stratastaffglobal.com'
END
WHERE r.slug = 'team-leader'
  AND u.full_name IN ('Leonard Sunga', 'Sheena Magdaraog', 'Trisha Balingit')
  AND (u.email IS NULL OR u.email = '');

CREATE TABLE IF NOT EXISTS staff_directory (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(150) NOT NULL UNIQUE,
  team_label VARCHAR(120) NOT NULL,
  email_domain VARCHAR(190) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_staff_directory_active_team(is_active, team_label, full_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS team_attendance_leave (
  attendance_id BIGINT UNSIGNED NOT NULL,
  staff_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (attendance_id, staff_id),
  KEY idx_team_attendance_leave_staff(staff_id, attendance_id),
  CONSTRAINT fk_team_attendance_leave_attendance FOREIGN KEY (attendance_id) REFERENCES team_attendance(id) ON DELETE CASCADE,
  CONSTRAINT fk_team_attendance_leave_staff FOREIGN KEY (staff_id) REFERENCES staff_directory(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO staff_directory(full_name, team_label, email_domain) VALUES
('Cyril Anne Bayaya', 'SME', 'jamesons.com.au'),
('Paul Henzon', 'Admin', 'jamesons.com.au'),
('Caissa Kiana Peña', 'Admin', 'jamesons.com.au'),
('Princess Fionna Cunanan', 'Admin', 'jamesons.com.au'),
('Mellen Roxas', 'Admin', 'jamesons.com.au'),
('Regie Manipon', 'Admin', 'jamesons.com.au'),
('Kaycee Soriano', 'Admin', 'jamesons.com.au'),
('Jim Lansangan', 'Admin', 'jamesons.com.au'),
('Prince Andrew Mangahas', 'Compliance', 'jamesons.com.au'),
('Abijah Dela Cruz', 'Compliance', 'jamesons.com.au'),
('Kelly Vital', 'Compliance', 'jamesons.com.au'),
('William Wallace Dizon', 'Compliance', 'jamesons.com.au'),
('Byran Dale Bernardo', 'Compliance', 'jamesons.com.au'),
('Harold Garcia', 'Compliance', 'jamesons.com.au'),
('Arnn Canlas', 'Compliance', 'jamesons.com.au'),
('Maria Daniella Cayanan', 'Compliance', 'jamesons.com.au'),
('Mary Angeline Dela Cruz', 'Insurance', 'jamesons.com.au'),
('Katherine Advincula', 'Insurance', 'jamesons.com.au'),
('Thea Janelle Waje', 'Insurance', 'jamesons.com.au'),
('Shaniah Dantes', 'Strata Care', 'jamesons.com.au'),
('Reynajoy Dacquel', 'Strata Care', 'jamesons.com.au'),
('Arman Estrada', 'Strata Care', 'jamesons.com.au'),
('Yansen Gray Mendoza', 'Strata Care', 'jamesons.com.au'),
('Ylla Legion', 'Strata Care', 'jamesons.com.au'),
('Mark Anthony Lorenzo', 'Strata Care', 'jamesons.com.au'),
('Bryan Nucup', 'Strata Care', 'jamesons.com.au'),
('Allen Pascua', 'Strata Care', 'jamesons.com.au'),
('Helga Rizza Bumagat', 'Strata Care', 'jamesons.com.au'),
('Jolianna Manguerra', 'Strata Care', 'jamesons.com.au'),
('Jay Anne Cohlene Sanchez', 'Strata Care', 'jamesons.com.au'),
('Kimberly Rose Ramos', 'Strata Care', 'jamesons.com.au'),
('Shan Keiki Galapon', 'Strata Care', 'jamesons.com.au'),
('Jamie Tapnio', 'Strata Care', 'jamesons.com.au'),
('Floyd Sanchez', 'Strata Care', 'jamesons.com.au'),
('Arhelo Mesina', 'Strata Care', 'jamesons.com.au'),
('Keannu Sicat', 'Strata Care', 'jamesons.com.au'),
('Trisha Mae Pineda', 'Strata Care', 'jamesons.com.au'),
('John Michael Orcilino', 'Strata Care', 'jamesons.com.au'),
('Divina Rabogo', 'CC Lead', 'jamesons.com.au'),
('Danmark Tabing', 'Customer Care', 'jamesons.com.au'),
('Crisden Jacob Sarate', 'Customer Care', 'jamesons.com.au'),
('Maria Carmel Requieron', 'Customer Care', 'jamesons.com.au'),
('Michael Allan Chester Mon', 'Customer Care', 'jamesons.com.au'),
('Reyby Arceo', 'Customer Care', 'jamesons.com.au'),
('Mark Diaz', 'Customer Care', 'jamesons.com.au'),
('Mark Anthony Labonete', 'Customer Care', 'jamesons.com.au'),
('Arnielyn Garcia', 'Customer Care', 'jamesons.com.au'),
('Paulyn Lino', 'R&M Lead', 'jamesons.com.au'),
('Rhon Romano', 'R&M Lead', 'jamesons.com.au'),
('John Henry Casingal', 'R&M Lead', 'jamesons.com.au'),
('Catherine Ronquillo', 'R&M', 'jamesons.com.au'),
('Carlyn Dizon', 'R&M', 'jamesons.com.au'),
('April Basilio', 'R&M', 'jamesons.com.au'),
('Lorden Jay Magsino', 'R&M', 'jamesons.com.au'),
('Rai Daniel Oliva', 'R&M', 'jamesons.com.au'),
('Crizian Lloyd Cabrera', 'R&M', 'jamesons.com.au'),
('Marcela Carambas', 'R&M', 'jamesons.com.au'),
('Alexis Sandra Garcia', 'R&M', 'jamesons.com.au'),
('Marjerie Santos', 'R&M', 'jamesons.com.au'),
('Elvin Sarmiento', 'R&M', 'jamesons.com.au'),
('Janella Fermin', 'R&M', 'jamesons.com.au'),
('Analyn Alcovendas', 'R&M', 'jamesons.com.au'),
('Princes Rociane Laxamana', 'R&M', 'jamesons.com.au'),
('Maricris Abia', 'R&M', 'jamesons.com.au'),
('Nico Cristobal', 'R&M Mid', 'jamesons.com.au'),
('Clarisse Fernandez', 'R&M Mid', 'jamesons.com.au'),
('Stefanie Estandian', 'R&M GY', 'jamesons.com.au'),
('Angelica Sampana', 'R&M GY', 'jamesons.com.au'),
('Joan Annaliza Tapnio', 'R&M GY', 'jamesons.com.au'),
('Carl Sundian', 'R&M GY', 'jamesons.com.au'),
('Leonard Sunga', 'TL', 'stratastaffglobal.com'),
('Sheena Magdaraog', 'TL', 'stratastaffglobal.com'),
('Trisha Balingit', 'TL', 'stratastaffglobal.com');
