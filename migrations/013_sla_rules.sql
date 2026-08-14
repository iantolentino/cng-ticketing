CREATE TABLE IF NOT EXISTS sla_rules (
  priority ENUM('low','normal','high','urgent') PRIMARY KEY,
  open_days INT UNSIGNED NOT NULL,
  idle_days INT UNSIGNED NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO sla_rules(priority,open_days,idle_days) VALUES
('low',10,5),('normal',7,3),('high',5,2),('urgent',2,1)
ON DUPLICATE KEY UPDATE priority=VALUES(priority);
