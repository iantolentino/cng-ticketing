CREATE TABLE IF NOT EXISTS system_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  level VARCHAR(20) NOT NULL,
  event VARCHAR(100) NOT NULL,
  message VARCHAR(500) NOT NULL,
  context JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_system_logs_created(created_at), KEY idx_system_logs_level_event(level,event)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
