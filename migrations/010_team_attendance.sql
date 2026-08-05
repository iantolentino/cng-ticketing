CREATE TABLE IF NOT EXISTS team_attendance (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    attendance_date DATE NOT NULL,
    department_id BIGINT UNSIGNED NOT NULL,
    logged_by BIGINT UNSIGNED NOT NULL,
    status ENUM('present','partial','absent','training','work_from_home') NOT NULL DEFAULT 'present',
    headcount INT UNSIGNED NOT NULL DEFAULT 0,
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_team_attendance_day_department(attendance_date, department_id),
    KEY idx_team_attendance_date(attendance_date, status),
    FOREIGN KEY(department_id) REFERENCES departments(id) ON DELETE CASCADE,
    FOREIGN KEY(logged_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
