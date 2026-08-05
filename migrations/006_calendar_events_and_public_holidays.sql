-- Additive migration for interactive Team Calendar events and 2026 PH/AU/CA public holidays.

ALTER TABLE company_holidays
  ADD COLUMN IF NOT EXISTS country_code VARCHAR(12) NOT NULL DEFAULT 'COMPANY' AFTER label,
  ADD COLUMN IF NOT EXISTS holiday_type VARCHAR(40) NOT NULL DEFAULT 'company' AFTER country_code;

ALTER TABLE company_holidays DROP INDEX IF EXISTS uq_company_holidays_date;
ALTER TABLE company_holidays
  ADD UNIQUE KEY IF NOT EXISTS uq_company_holidays_scope (`date`, country_code, label);

CREATE TABLE IF NOT EXISTS calendar_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(180) NOT NULL,
  event_date DATE NOT NULL,
  end_date DATE NULL,
  event_type ENUM('team_event','coverage','reminder','other') NOT NULL DEFAULT 'team_event',
  created_by BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_calendar_events_dates(event_date,end_date),
  FOREIGN KEY(created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO company_holidays(`date`,label,country_code,holiday_type) VALUES
('2026-01-01','New Year''s Day','PH','public'),
('2026-01-17','Chinese New Year','PH','public'),
('2026-02-25','EDSA People Power Revolution Anniversary','PH','public'),
('2026-04-02','Maundy Thursday','PH','public'),
('2026-04-03','Good Friday','PH','public'),
('2026-04-04','Black Saturday','PH','public'),
('2026-04-09','Araw ng Kagitingan','PH','public'),
('2026-05-01','Labor Day','PH','public'),
('2026-06-12','Independence Day','PH','public'),
('2026-08-21','Ninoy Aquino Day','PH','public'),
('2026-08-31','National Heroes Day','PH','public'),
('2026-11-01','All Saints Day','PH','public'),
('2026-11-02','All Souls'' Day','PH','public'),
('2026-11-30','Bonifacio Day','PH','public'),
('2026-12-08','Feast of the Immaculate Conception of Mary','PH','public'),
('2026-12-24','Christmas Eve','PH','public'),
('2026-12-25','Christmas Day','PH','public'),
('2026-12-30','Rizal Day','PH','public'),
('2026-12-31','Last Day of the Year','PH','public'),
('2026-01-01','New Year''s Day','AU','public'),
('2026-01-26','Australia Day','AU','public'),
('2026-04-03','Good Friday','AU','public'),
('2026-04-04','Easter Saturday','AU','public'),
('2026-04-06','Easter Monday','AU','public'),
('2026-04-25','Anzac Day','AU','public'),
('2026-04-27','Anzac Day Public Holiday','AU','public'),
('2026-06-08','Birthday of the Sovereign','AU','public'),
('2026-09-28','Birthday of the Sovereign - WA','AU','public'),
('2026-10-05','Birthday of the Sovereign - QLD','AU','public'),
('2026-12-25','Christmas Day','AU','public'),
('2026-12-26','Boxing Day','AU','public'),
('2026-12-29','Annual close down','AU','public'),
('2026-12-30','Annual close down','AU','public'),
('2026-12-31','Annual close down','AU','public'),
('2026-01-01','New Year','CA','public'),
('2026-04-03','Good Friday','CA','public'),
('2026-04-06','Easter Monday','CA','public'),
('2026-05-18','Victoria Day','CA','public'),
('2026-06-24','Saint-Jean-Baptiste Day - Quebec','CA','public'),
('2026-07-01','Canada Day','CA','public'),
('2026-08-03','Civic Holiday','CA','public'),
('2026-09-07','Labour Day','CA','public'),
('2026-09-30','National Day for Truth and Reconciliation','CA','public'),
('2026-10-12','Thanksgiving Day','CA','public'),
('2026-11-11','Remembrance Day','CA','public'),
('2026-12-25','Christmas Day','CA','public'),
('2026-12-26','Boxing Day','CA','public')
ON DUPLICATE KEY UPDATE holiday_type=VALUES(holiday_type);
