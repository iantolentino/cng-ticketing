-- Additive migration for leave request supporting files shown from Team Calendar details.

CREATE TABLE IF NOT EXISTS leave_request_attachments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  leave_request_id BIGINT UNSIGNED NOT NULL,
  uploaded_by BIGINT UNSIGNED NOT NULL,
  file_name VARCHAR(255) NOT NULL,
  file_path VARCHAR(500) NOT NULL,
  mime_type VARCHAR(120) NOT NULL,
  file_size BIGINT UNSIGNED NOT NULL,
  uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_leave_request_attachments_request(leave_request_id),
  FOREIGN KEY(leave_request_id) REFERENCES leave_requests(id) ON DELETE CASCADE,
  FOREIGN KEY(uploaded_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
